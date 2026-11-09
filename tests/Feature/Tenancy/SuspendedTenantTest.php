<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendedTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_ditangguhkan_boleh_membaca_tetapi_tidak_boleh_menulis(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create();
        $staff = User::factory()->forTenant($tenant)->staff()->create();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('owner.staff.index'))->assertOk();

        $this->actingAs($owner)
            ->post(route('owner.staff.invite'), ['email' => 'baru@contoh.id'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('owner.staff.destroy', $staff))
            ->assertForbidden();
    }

    public function test_tenant_ditangguhkan_tetap_bisa_mengekspor_dan_keluar(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $this->actingAs($owner)->get(route('owner.export'))->assertOk();
        $this->actingAs($owner)->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_pengguna_nonaktif_dikeluarkan(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->forTenant($tenant)->staff()->create();
        $staff->forceFill(['is_active' => false])->saveQuietly();

        $this->actingAs($staff)->get(route('owner.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_tanpa_tenant_tidak_masuk_panel_pemilik(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('owner.dashboard'))->assertForbidden();
    }
}
