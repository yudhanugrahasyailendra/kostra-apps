<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_masuk_dengan_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->owner()->create(['email' => 'pemilik@contoh.id']);

        $this->post(route('login'), ['login' => 'Pemilik@Contoh.id', 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_masuk_dengan_nomor_whatsapp_lokal(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->owner()->create(['email' => null, 'phone' => '+6281234567890']);

        $this->post(route('login'), ['login' => '081234567890', 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->owner()->create(['email' => 'a@contoh.id']);

        $this->post(route('login'), ['login' => 'a@contoh.id', 'password' => 'salah'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->staff()->create(['email' => 'n@contoh.id']);
        $user->forceFill(['is_active' => false])->saveQuietly();

        $this->post(route('login'), ['login' => 'n@contoh.id', 'password' => 'password'])
            ->assertSessionHasErrors('login');
    }

    public function test_dibatasi_setelah_lima_percobaan_gagal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['login' => 'x@contoh.id', 'password' => 'salah']);
        }

        $this->post(route('login'), ['login' => 'x@contoh.id', 'password' => 'salah'])
            ->assertSessionHasErrors('login');

        $this->assertStringContainsString('Terlalu banyak', session('errors')->get('login')[0]);
    }

    public function test_keluar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->owner()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('owner.dashboard'))->assertRedirect(route('login'));
    }
}
