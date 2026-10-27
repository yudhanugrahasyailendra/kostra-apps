<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->withStaffRoom()->create();
        $this->owner = User::factory()->for($this->tenant)->create();
    }

    public function test_permission_change_is_audited_with_old_and_new_values(): void
    {
        $staff = User::factory()->for($this->tenant)->staff(['finance.view'])->create();

        $this->actingAs($this->owner)
            ->put(route('owner.staff.update', $staff), ['permissions' => ['settings.manage']])
            ->assertRedirect();

        $log = $this->withoutTenant(fn () => AuditLog::where('event', AuditEvent::Updated->value)
            ->where('auditable_type', 'user')
            ->where('auditable_id', $staff->id)
            ->latest('id')->firstOrFail());

        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame(['finance.view'], $log->old_values['permissions']);
        $this->assertSame(['settings.manage'], $log->new_values['permissions']);
    }

    public function test_creation_is_audited_and_scoped_to_the_tenant(): void
    {
        $this->assertTrue($this->withoutTenant(fn () => AuditLog::where('event', 'created')
            ->where('auditable_type', 'user')
            ->where('auditable_id', $this->owner->id)
            ->where('tenant_id', $this->tenant->id)
            ->exists()));

        $other = Tenant::factory()->create();
        $this->assertSame(0, $this->asTenant($other, fn () => AuditLog::where('auditable_id', $this->owner->id)
            ->where('auditable_type', 'user')->count()));
    }

    public function test_secrets_never_reach_the_audit_log(): void
    {
        $this->owner->update(['password' => 'sandi-baru-123', 'name' => 'Nama Baru']);

        $logs = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'user')
            ->where('auditable_id', $this->owner->id)->get());

        foreach ($logs as $log) {
            $payload = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString('password', $payload);
            $this->assertStringNotContainsString('remember_token', $payload);
        }

        $this->assertTrue($logs->contains(fn ($log) => ($log->new_values['name'] ?? null) === 'Nama Baru'));
    }

    public function test_invitation_token_hash_is_not_audited(): void
    {
        $this->actingAs($this->owner)->post(route('owner.staff.invite'), ['email' => 'staf@example.com']);

        $log = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'invitation')->firstOrFail());

        $this->assertArrayNotHasKey('token_hash', $log->new_values);
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = $this->withoutTenant(fn () => AuditLog::firstOrFail());

        try {
            $log->update(['event' => 'deleted']);
            $this->fail('Update seharusnya ditolak.');
        } catch (LogicException) {
            // diharapkan
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
