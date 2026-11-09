<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_perubahan_penting_tercatat_dengan_nilai_lama_dan_baru(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Kos Lama']);
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $this->actingAs($owner);

        TenantContext::instance()->runAs($tenant, function () use ($tenant) {
            $tenant->update(['name' => 'Kos Baru']);
        });

        $log = TenantContext::instance()->unscoped(fn () => AuditLog::query()
            ->where('event', 'updated')
            ->where('auditable_type', $tenant->getMorphClass())
            ->latest('id')->first());

        $this->assertNotNull($log);
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame(['name' => 'Kos Lama'], $log->old_values);
        $this->assertSame(['name' => 'Kos Baru'], $log->new_values);
    }

    public function test_kata_sandi_dan_token_tidak_pernah_masuk_audit(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->owner()->create();

        $logs = TenantContext::instance()->unscoped(fn () => AuditLog::query()
            ->where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)->get());

        $this->assertNotEmpty($logs);

        foreach ($logs as $log) {
            $payload = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString('password', $payload);
            $this->assertStringNotContainsString('remember_token', $payload);
        }
    }

    public function test_log_audit_tidak_dapat_diubah_atau_dihapus(): void
    {
        $tenant = Tenant::factory()->create();
        $log = TenantContext::instance()->unscoped(fn () => AuditLog::query()->firstOrFail());

        try {
            $log->update(['event' => 'dipalsukan']);
            $this->fail('Update log audit seharusnya ditolak.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
