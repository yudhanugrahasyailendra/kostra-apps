<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->for($this->tenantA)->create();
        $this->ownerB = User::factory()->for($this->tenantB)->create();
        $this->staffB = User::factory()->for($this->tenantB)->staff()->create();
    }

    public function test_find_by_id_of_another_tenant_returns_nothing(): void
    {
        $this->asTenant($this->tenantA, function () {
            $this->assertNull(User::find($this->staffB->id));
            $this->assertNull(User::find($this->ownerB->id));
            $this->assertSame([$this->ownerA->id], User::pluck('id')->all());
        });
    }

    public function test_update_and_delete_of_another_tenant_are_rejected(): void
    {
        $this->asTenant($this->tenantA, function () {
            $this->assertSame(0, User::where('id', $this->staffB->id)->update(['name' => 'Diretas']));
            $this->assertSame(0, User::where('id', $this->staffB->id)->delete());
        });

        $fresh = $this->withoutTenant(fn () => User::find($this->staffB->id));
        $this->assertNotNull($fresh);
        $this->assertNotSame('Diretas', $fresh->name);
    }

    public function test_without_a_tenant_context_queries_fail_closed(): void
    {
        $this->assertSame(0, User::count());
        $this->assertSame(0, Invitation::count());
    }

    public function test_explicit_without_tenant_sees_every_tenant(): void
    {
        $this->assertSame(3, $this->withoutTenant(fn () => User::count()));
    }

    public function test_context_is_restored_after_run(): void
    {
        $this->asTenant($this->tenantA, fn () => null);

        $this->assertNull(app(TenantContext::class)->id());
        $this->assertSame(0, User::count());
    }

    public function test_models_get_tenant_id_automatically(): void
    {
        $invitation = $this->asTenant($this->tenantA, fn () => Invitation::create([
            'invited_by' => $this->ownerA->id,
            'role' => UserRole::Staff,
            'email' => 'baru@example.com',
            'token_hash' => hash('sha256', 'abc'),
            'expires_at' => now()->addDay(),
        ]));

        $this->assertSame($this->tenantA->id, $invitation->tenant_id);
    }

    public function test_creating_with_a_different_tenant_id_is_refused(): void
    {
        $this->expectException(LogicException::class);

        $this->asTenant($this->tenantA, fn () => Invitation::create([
            'tenant_id' => $this->tenantB->id,
            'invited_by' => $this->ownerA->id,
            'role' => UserRole::Staff,
            'email' => 'baru@example.com',
            'token_hash' => hash('sha256', 'abc'),
            'expires_at' => now()->addDay(),
        ]));
    }

    public function test_route_model_binding_cannot_reach_another_tenants_staff(): void
    {
        $this->actingAs($this->ownerA)
            ->put(route('owner.staff.update', $this->staffB), ['permissions' => ['finance.view']])
            ->assertNotFound();

        $this->assertSame([], $this->withoutTenant(fn () => User::find($this->staffB->id))->permissions);
    }

    public function test_another_tenants_invitation_cannot_be_revoked(): void
    {
        $invitation = $this->asTenant($this->tenantB, fn () => Invitation::create([
            'invited_by' => $this->ownerB->id,
            'role' => UserRole::Staff,
            'email' => 'tamu@example.com',
            'token_hash' => hash('sha256', 'xyz'),
            'expires_at' => now()->addDay(),
        ]));

        $this->actingAs($this->ownerA)
            ->delete(route('owner.staff.invitations.revoke', $invitation))
            ->assertNotFound();

        $this->assertTrue($this->withoutTenant(fn () => Invitation::find($invitation->id))->isUsable());
    }

    public function test_staff_list_only_shows_own_tenant(): void
    {
        $staffA = User::factory()->for($this->tenantA)->staff()->create(['name' => 'Staf Tenant A']);

        $this->actingAs($this->ownerA)
            ->get(route('owner.staff.index'))
            ->assertOk()
            ->assertSee($staffA->name)
            ->assertDontSee($this->staffB->name);
    }
}
