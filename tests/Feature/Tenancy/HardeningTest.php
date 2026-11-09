<?php

namespace Tests\Feature\Tenancy;

use App\Actions\InviteStaff;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMismatchException;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_ditutup_hanya_boleh_membaca(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Closed]);
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'x@contoh.id'])->assertForbidden();
    }

    public function test_undangan_ke_tenant_ditangguhkan_tidak_bisa_diterima(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $result = TenantContext::instance()->runAs($tenant, fn () => app(InviteStaff::class)
            ->handle($owner, ['email' => 'staf@contoh.id']));

        $tenant->forceFill(['status' => TenantStatus::Suspended])->save();

        $this->get(route('invitation.show', $result['token']))
            ->assertInertia(fn ($page) => $page->where('usable', false));

        $this->post(route('invitation.accept', $result['token']), [
            'name' => 'Budi', 'email' => 'staf@contoh.id',
            'password' => 'rahasia-banget', 'password_confirmation' => 'rahasia-banget',
        ])->assertSessionHasErrors('token');
    }

    public function test_penerima_tidak_bisa_mengganti_email_yang_diundang(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $result = TenantContext::instance()->runAs($tenant, fn () => app(InviteStaff::class)
            ->handle($owner, ['email' => 'asli@contoh.id']));

        $this->post(route('invitation.accept', $result['token']), [
            'name' => 'Budi', 'email' => 'lain@contoh.id',
            'password' => 'rahasia-banget', 'password_confirmation' => 'rahasia-banget',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'asli@contoh.id', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('users', ['email' => 'lain@contoh.id']);
    }

    public function test_membuat_model_tenant_tanpa_konteks_ditolak(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $this->expectException(TenantMismatchException::class);

        Invitation::factory()->state(['invited_by' => $owner->id])->create();
    }

    public function test_tenant_id_tidak_boleh_diubah(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenantA)->staff()->create();

        $this->expectException(TenantMismatchException::class);

        TenantContext::instance()->runAs($tenantA, function () use ($user, $tenantB) {
            $user->tenant_id = $tenantB->id;
            $user->save();
        });
    }

    public function test_ekspor_menetralkan_injeksi_formula_tetapi_menjaga_nomor_telepon(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create(['name' => '=HYPERLINK("http://jahat")', 'phone' => '+6281234567890']);

        $response = $this->actingAs($owner)->get(route('owner.export'))->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $csv = $zip->getFromName('pengguna.csv');
        $zip->close();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('+6281234567890', $csv);
        $this->assertStringNotContainsString("'+6281234567890", $csv);
    }

    public function test_seeder_super_admin_menolak_email_milik_akun_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->owner()->create(['email' => 'pemilik@contoh.id']);

        config(['kostra.admin.email' => 'pemilik@contoh.id', 'kostra.admin.password' => 'rahasia-banget']);

        $this->expectException(RuntimeException::class);

        $this->seed(SuperAdminSeeder::class);
    }

    public function test_seeder_super_admin_membuat_dan_memulihkan_akun_tanpa_tenant(): void
    {
        config(['kostra.admin.email' => 'admin@kostra.id', 'kostra.admin.password' => 'rahasia-banget']);

        $this->seed(SuperAdminSeeder::class);

        $admin = TenantContext::instance()->unscoped(fn () => User::query()->where('email', 'admin@kostra.id')->firstOrFail());
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertNull($admin->tenant_id);

        TenantContext::instance()->unscoped(fn () => $admin->delete());
        $this->seed(SuperAdminSeeder::class);

        $this->assertNull(TenantContext::instance()->unscoped(fn () => User::withTrashed()->find($admin->id))->deleted_at);
    }

    public function test_grup_route_admin_hanya_untuk_super_admin(): void
    {
        Route::middleware(['web', 'auth', 'super_admin'])->get('/_uji/admin', fn () => 'ok');

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->forTenant($tenant)->owner()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->get('/_uji/admin')->assertRedirect(route('login'));
        $this->actingAs($owner)->get('/_uji/admin')->assertForbidden();
        $this->actingAs($admin)->get('/_uji/admin')->assertOk();
    }
}
