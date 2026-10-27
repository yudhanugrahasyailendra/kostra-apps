<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndMenuTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    public function test_staff_without_permissions_does_not_see_finance_settings_or_subscription_menus(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)->get('/owner')
            ->assertOk()
            ->assertDontSee('Pengeluaran')
            ->assertDontSee('Pengaturan')
            ->assertDontSee('Langganan');

        $this->actingAs($staff)->get('/owner/settings')->assertForbidden();
    }

    public function test_staff_menu_follows_granted_permissions(): void
    {
        $staff = User::factory()->for($this->tenant)->staff(['finance.view'])->create();
        $this->actingAs($staff)->get('/owner')->assertSee('Pengeluaran')->assertDontSee('Pengaturan')->assertDontSee('Langganan');

        $staff = User::factory()->for($this->tenant)->staff(['settings.manage'])->create();
        $this->actingAs($staff)->get('/owner')->assertSee('Pengaturan')->assertDontSee('Pengeluaran')->assertDontSee('Langganan');
        $this->actingAs($staff)->get('/owner/settings')->assertOk();
    }

    public function test_owner_sees_every_menu(): void
    {
        $owner = User::factory()->for($this->tenant)->create();

        $this->actingAs($owner)->get('/owner')
            ->assertSee('Laporan Keuangan')
            ->assertSee('Pengaturan Kos')
            ->assertSee('Kuota WhatsApp')
            ->assertSee('Upgrade');
    }

    public function test_only_super_admin_can_open_admin_panel(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $staff = User::factory()->for($this->tenant)->staff()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->actingAs($staff)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_super_admin_cannot_open_the_owner_panel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/owner')->assertForbidden();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $owner = User::factory()->for($this->tenant)->inactive()->create();

        $this->actingAs($owner)->get('/owner')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
