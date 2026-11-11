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

/** Bagian daftar di dashboard pemilik: tagihan terdekat, antrean bukti, komplain, denah kamar, kuota. */
class DashboardWidgetsTest extends TestCase
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

    /** @return array{lease: Lease, room: Room} Satu kamar terisi dengan satu tagihan menunggak, satu bukti pending, satu komplain. */
    private function seedTenant(Tenant $tenant): array
    {
        return $this->inTenant($tenant, function () {
            $property = Property::factory()->create();
            $room = Room::factory()->create(['property_id' => $property->id, 'floor' => 1]);
            $lease = Lease::factory()->create([
                'property_id' => $property->id, 'room_id' => $room->id,
                'primary_resident_id' => Resident::factory()->create()->id, 'start_date' => '2026-10-01',
            ]);
            $invoice = Invoice::factory()->forLease($lease)->create([
                'period' => null, 'due_date' => '2026-10-28', 'total' => 1800000, 'subtotal' => 1800000,
            ]);
            PaymentProof::factory()->create(['invoice_id' => $invoice->id, 'resident_id' => $invoice->resident_id, 'amount' => 1800000]);
            Complaint::factory()->create(['property_id' => $property->id, 'room_id' => $room->id, 'resident_id' => $invoice->resident_id]);

            return ['lease' => $lease, 'room' => $room];
        });
    }

    public function test_dashboard_memuat_daftar_ringkas_sesuai_data_tenant(): void
    {
        $seeded = $this->seedTenant($this->tenantA);

        $this->actingAs($this->ownerA)->get('/app')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Owner/Dashboard')
                ->where('summary.due_soon.overdue_count', 1)
                ->where('summary.due_soon.items.0.outstanding', 1800000)
                ->where('summary.due_soon.items.0.room_number', $seeded['room']->number)
                ->where('summary.pending_proofs.count', 1)
                ->where('summary.pending_proofs.items.0.amount', 1800000)
                ->where('summary.complaints.count', 1)
                ->where('summary.room_map.0.floor', 1)
                ->where('summary.room_map.0.rooms.0.number', $seeded['room']->number)
                ->has('summary.quota.limit'),
        );
    }

    public function test_dashboard_tidak_memuat_data_tenant_lain(): void
    {
        $this->seedTenant($this->tenantA);
        $this->seedTenant($this->tenantB);
        $this->inTenant($this->tenantB, fn () => Room::factory()->create(['property_id' => Property::factory()->create()->id, 'floor' => 7]));

        $this->actingAs($this->ownerA)->get('/app')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('summary.due_soon.overdue_count', 1)
                ->where('summary.pending_proofs.count', 1)
                ->where('summary.complaints.count', 1)
                ->has('summary.room_map', 1)
                ->where('summary.room_map.0.floor', 1),
        );
    }

    public function test_staf_tanpa_finance_view_tidak_menerima_tagihan_dan_bukti(): void
    {
        $this->seedTenant($this->tenantA);

        $this->actingAs($this->staffNoFinance)->get('/app')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('summary.due_soon', null)
                ->where('summary.pending_proofs', null)
                ->where('summary.complaints.count', 1)
                ->has('summary.room_map'),
        );
    }

    public function test_props_dashboard_tidak_memuat_kolom_terlarang(): void
    {
        $this->seedTenant($this->tenantA);

        $json = json_encode($this->actingAs($this->ownerA)->get('/app')->assertOk()->viewData('page')['props']['summary']);

        foreach (['token_hash', 'tenant_id', 'password', 'phone', 'storage_path', 'path'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $json);
        }
    }
}
