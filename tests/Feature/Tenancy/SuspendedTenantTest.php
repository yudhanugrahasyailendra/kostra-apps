<?php

namespace Tests\Feature\Tenancy;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendedTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_tenant_can_read_but_not_write(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = User::factory()->for($tenant)->create();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('owner.staff.index'))->assertOk();

        $this->actingAs($owner)
            ->post(route('owner.staff.invite'), ['email' => 'baru@example.com'])
            ->assertForbidden();

        $this->assertSame(0, $this->asTenant($tenant, fn () => Invitation::count()));
    }

    public function test_closed_tenant_cannot_use_the_panel(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'closed']);
        $owner = User::factory()->for($tenant)->create();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertForbidden();
    }

    public function test_active_tenant_can_write(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();

        $this->actingAs($owner)
            ->post(route('owner.staff.invite'), ['email' => 'baru@example.com'])
            ->assertRedirect(route('owner.staff.index'));
    }
}
