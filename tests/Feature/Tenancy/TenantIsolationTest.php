<?php

namespace Tests\Feature\Tenancy;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMismatchException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffB = User::factory()->forTenant($this->tenantB)->staff()->create();
    }

    public function test_find_data_tenant_lain_mengembalikan_kosong(): void
    {
        TenantContext::instance()->runAs($this->tenantA, function () {
            $this->assertNull(User::query()->find($this->staffB->id));
            $this->assertSame(1, User::query()->count());
        });
    }

    public function test_tanpa_tenant_aktif_query_gagal_tertutup(): void
    {
        $this->assertSame(0, User::query()->count());
    }

    public function test_ubah_dan_hapus_data_tenant_lain_tidak_mengenai_baris(): void
    {
        TenantContext::instance()->runAs($this->tenantA, function () {
            $this->assertSame(0, User::query()->whereKey($this->staffB->id)->update(['name' => 'Diretas']));
            $this->assertSame(0, User::query()->whereKey($this->staffB->id)->delete());
        });

        $this->assertSame($this->staffB->name, TenantContext::instance()->unscoped(
            fn () => User::query()->find($this->staffB->id)->name
        ));
    }

    public function test_model_baru_mengisi_tenant_id_otomatis(): void
    {
        $invitation = TenantContext::instance()->runAs($this->tenantA, fn () => Invitation::factory()
            ->state(['invited_by' => $this->ownerA->id])
            ->create());

        $this->assertSame($this->tenantA->id, $invitation->tenant_id);
    }

    public function test_membuat_data_dengan_tenant_id_lain_ditolak(): void
    {
        $this->expectException(TenantMismatchException::class);

        TenantContext::instance()->runAs($this->tenantA, fn () => Invitation::factory()
            ->state(['invited_by' => $this->ownerA->id, 'tenant_id' => $this->tenantB->id])
            ->create());
    }

    public function test_route_model_binding_staf_tenant_lain_404(): void
    {
        $staffA = User::factory()->forTenant($this->tenantA)->staff()->create();

        $this->actingAs($this->ownerA)
            ->patch(route('owner.staff.update', $this->staffB), ['permissions' => []])
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->delete(route('owner.staff.destroy', $this->staffB))
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->patch(route('owner.staff.update', $staffA), ['permissions' => ['finance.view']])
            ->assertRedirect();
    }

    public function test_daftar_staf_hanya_memuat_tenant_sendiri(): void
    {
        $staffA = User::factory()->forTenant($this->tenantA)->staff()->create();

        $this->actingAs($this->ownerA)
            ->get(route('owner.staff.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Owner/Staff/Index')
                ->has('staff', 2) // pemilik + satu staf, tanpa pengguna tenant B
                ->where('staff.0.id', $this->ownerA->id)
                ->where('staff.1.id', $staffA->id));
    }

    public function test_log_audit_tenant_lain_tidak_terlihat(): void
    {
        $this->actingAs($this->ownerA)
            ->get(route('owner.audit.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Owner/AuditLog/Index')
                ->where('logs', fn ($logs) => collect($logs)->every(
                    fn ($log) => ! str_contains((string) json_encode($log), $this->staffB->email)
                ))
                ->where('users', fn ($users) => collect($users)->pluck('id')->doesntContain($this->staffB->id)));
    }

    public function test_filter_log_audit_dengan_pengguna_tenant_lain_tidak_membocorkan(): void
    {
        $this->actingAs($this->ownerA)
            ->get(route('owner.audit.index', ['user_id' => $this->staffB->id]))
            ->assertInertia(fn ($page) => $page->has('logs', 0)->where('pagination.total', 0));
    }
}
