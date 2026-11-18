<?php

namespace Tests\Feature\Reports;

use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Lonceng notifikasi di kepala semua halaman pemilik: hitungan bersama lewat props `notifications`. */
class OwnerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffNoFinance;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffNoFinance = User::factory()->forTenant($this->tenantA)->staff()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    /** Satu kontrak berakhir 25 November, satu tagihan menunggak Rp1.800.000, satu bukti pending, satu komplain terbuka. */
    private function seedTenant(Tenant $tenant): void
    {
        $this->inTenant($tenant, function () {
            $property = Property::factory()->create();
            $room = Room::factory()->create(['property_id' => $property->id]);
            $lease = Lease::factory()->create([
                'property_id' => $property->id, 'room_id' => $room->id,
                'primary_resident_id' => Resident::factory()->create()->id,
                'start_date' => '2026-10-01', 'end_date' => '2026-11-25',
            ]);
            $invoice = Invoice::factory()->forLease($lease)->create([
                'period' => null, 'due_date' => '2026-10-28', 'total' => 1800000, 'subtotal' => 1800000,
            ]);
            PaymentProof::factory()->create(['invoice_id' => $invoice->id, 'resident_id' => $invoice->resident_id, 'amount' => 1800000]);
            Complaint::factory()->create(['property_id' => $property->id, 'room_id' => $room->id, 'resident_id' => $invoice->resident_id]);
        });
    }

    public function test_notifikasi_dibagikan_ke_semua_halaman_pemilik(): void
    {
        $this->seedTenant($this->tenantA);

        foreach (['/app', '/app/kamar', '/app/komplain'] as $url) {
            $this->actingAs($this->ownerA)->get($url)->assertOk()->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('notifications.pending_proofs', 1)
                    ->where('notifications.overdue_invoices.count', 1)
                    ->where('notifications.overdue_invoices.amount', 1800000)
                    ->where('notifications.open_complaints', 1)
                    ->where('notifications.expiring_leases.count', 1)
                    ->where('notifications.expiring_leases.within_days', 30)
                    ->has('notifications.quota.limit')
                    ->has('notifications.quota.remaining'),
            );
        }
    }

    public function test_notifikasi_sama_dengan_angka_dashboard(): void
    {
        $this->seedTenant($this->tenantA);

        $props = $this->actingAs($this->ownerA)->get('/app')->assertOk()->viewData('page')['props'];

        $this->assertSame($props['summary']['pending_proofs']['count'], $props['notifications']['pending_proofs']);
        $this->assertSame($props['summary']['due_soon']['overdue_count'], $props['notifications']['overdue_invoices']['count']);
        $this->assertSame($props['summary']['complaints']['count'], $props['notifications']['open_complaints']);
        $this->assertSame($props['summary']['expiring_leases']['count'], $props['notifications']['expiring_leases']['count']);
    }

    public function test_notifikasi_tidak_memuat_data_tenant_lain(): void
    {
        $this->seedTenant($this->tenantA);
        $this->seedTenant($this->tenantB);

        $this->actingAs($this->ownerA)->get('/app/kamar')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('notifications.pending_proofs', 1)
                ->where('notifications.overdue_invoices.count', 1)
                ->where('notifications.overdue_invoices.amount', 1800000)
                ->where('notifications.open_complaints', 1)
                ->where('notifications.expiring_leases.count', 1),
        );
    }

    public function test_staf_tanpa_finance_view_tidak_menerima_notifikasi_keuangan(): void
    {
        $this->seedTenant($this->tenantA);

        $this->actingAs($this->staffNoFinance)->get('/app/kamar')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('notifications.pending_proofs', null)
                ->where('notifications.overdue_invoices', null)
                ->where('notifications.open_complaints', 1)
                ->where('notifications.expiring_leases.count', 1),
        );
    }

    public function test_notifikasi_tidak_dibagikan_di_luar_area_pemilik(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('notifications', null));
    }

    public function test_props_notifikasi_tidak_memuat_kolom_terlarang(): void
    {
        $this->seedTenant($this->tenantA);

        $json = json_encode($this->actingAs($this->ownerA)->get('/app/kamar')->assertOk()->viewData('page')['props']['notifications']);

        foreach (['token_hash', 'tenant_id', 'password', 'phone', 'name', 'resident_name', 'path'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $json);
        }
    }
}
