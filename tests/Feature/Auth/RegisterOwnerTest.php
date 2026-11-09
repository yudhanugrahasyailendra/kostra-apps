<?php

namespace Tests\Feature\Auth;

use App\Actions\RegisterOwner;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_daftar_dengan_email_membuat_tenant_gratis_dan_pemilik(): void
    {
        $this->post(route('register'), [
            'kos_name' => 'Kos Griya Harmoni',
            'name' => 'Hendra',
            'email' => 'Hendra@Contoh.ID',
            'password' => 'rahasia-banget',
            'password_confirmation' => 'rahasia-banget',
        ])->assertRedirect(route('owner.dashboard'));

        $user = TenantContext::instance()->unscoped(fn () => User::query()->where('email', 'hendra@contoh.id')->firstOrFail());
        $tenant = Tenant::query()->findOrFail($user->tenant_id);

        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertSame('free', $tenant->plan->key);
        $this->assertSame('Kos Griya Harmoni', $tenant->name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_daftar_dengan_nomor_whatsapp_dinormalisasi(): void
    {
        $this->post(route('register'), [
            'kos_name' => 'Kos Mawar',
            'name' => 'Sari',
            'phone' => '0812-3456-7890',
            'password' => 'rahasia-banget',
            'password_confirmation' => 'rahasia-banget',
        ])->assertRedirect(route('owner.dashboard'));

        $this->assertDatabaseHas('users', ['phone' => '+6281234567890', 'email' => null]);
    }

    public function test_email_atau_nomor_wajib_salah_satu(): void
    {
        $this->post(route('register'), [
            'kos_name' => 'Kos X', 'name' => 'X', 'password' => 'rahasia-banget', 'password_confirmation' => 'rahasia-banget',
        ])->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_email_ganda_ditolak_dengan_pesan_indonesia(): void
    {
        User::factory()->superAdmin()->create(['email' => 'ada@contoh.id']);

        $this->post(route('register'), [
            'kos_name' => 'Kos X', 'name' => 'X', 'email' => 'ada@contoh.id',
            'password' => 'rahasia-banget', 'password_confirmation' => 'rahasia-banget',
        ])->assertSessionHasErrors(['email' => 'Email sudah terdaftar.']);
    }

    public function test_pendaftaran_atomik_bila_gagal_tidak_ada_sisa_data(): void
    {
        Plan::query()->where('key', 'free')->delete();

        try {
            app(RegisterOwner::class)->handle([
                'kos_name' => 'Kos Gagal', 'name' => 'Gagal', 'email' => 'gagal@contoh.id', 'password' => 'rahasia-banget',
            ]);
            $this->fail('Seharusnya gagal karena paket Gratis tidak ada.');
        } catch (\Throwable $e) {
            // diharapkan: firstOrFail gagal sebelum ada data yang dibuat
        }

        $this->assertSame(0, Tenant::query()->count());
        $this->assertSame(0, TenantContext::instance()->unscoped(fn () => User::query()->count()));
    }

    public function test_kegagalan_di_tengah_transaksi_membatalkan_tenant(): void
    {
        // Pengguna dengan email yang sama sudah ada: insert pengguna gagal setelah tenant dibuat.
        User::factory()->superAdmin()->create(['email' => 'sama@contoh.id']);

        try {
            app(RegisterOwner::class)->handle([
                'kos_name' => 'Kos Setengah', 'name' => 'S', 'email' => 'sama@contoh.id', 'password' => 'rahasia-banget',
            ]);
            $this->fail('Seharusnya gagal karena email ganda.');
        } catch (\Throwable) {
            // diharapkan
        }

        $this->assertSame(0, Tenant::query()->where('name', 'Kos Setengah')->count());
    }
}
