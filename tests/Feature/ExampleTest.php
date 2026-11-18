<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_utama_mengarahkan_tamu_ke_form_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_utama_mengarahkan_pemilik_ke_dashboard(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->owner()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('owner.dashboard'));
    }

    public function test_halaman_utama_mengarahkan_super_admin_ke_panel_admin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/')->assertRedirect(route('admin.tenants.index'));
    }
}
