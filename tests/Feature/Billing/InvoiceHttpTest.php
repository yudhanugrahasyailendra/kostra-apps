<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Lease;
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

class InvoiceHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffNoFinance;

    private User $staffFinance;

    private User $ownerB;

    private Property $propertyA;

    private Property $propertyB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffNoFinance = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->staffFinance = User::factory()->forTenant($this->tenantA)->staff(['finance.view'])->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->propertyA = $this->inTenant($this->tenantA, fn () => Property::factory()->create());
        $this->propertyB = $this->inTenant($this->tenantB, fn () => Property::factory()->create());
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

    private function leaseIn(Tenant $tenant, Property $property): Lease
    {
        return $this->inTenant($tenant, function () use ($property) {
            $room = Room::factory()->create(['property_id' => $property->id]);
            $resident = Resident::factory()->create();

            return Lease::factory()->create(['property_id' => $property->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id]);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoiceIn(Tenant $tenant, Lease $lease, array $attributes = []): Invoice
    {
        return $this->inTenant($tenant, fn () => Invoice::factory()->forLease($lease)->create($attributes));
    }

    // Daftar

    public function test_daftar_menampilkan_tagihan_bulan_berjalan_dengan_props_aman(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);
        $this->invoiceIn($this->tenantA, $lease);
        $this->invoiceIn($this->tenantA, $lease, ['period' => '2026-10']);

        $this->actingAs($this->ownerA)->get('/app/tagihan')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Invoices/Index')
                ->has('invoices', 1)
                ->where('invoices.0.period', '2026-11')
                ->where('invoices.0.total', 1500000)
                ->where('invoices.0.outstanding', 1500000)
                ->where('invoices.0.can.void', true)
                ->missing('invoices.0.tenant_id')
                ->missing('invoices.0.phone')
                ->missing('invoices.0.resident_phone')
                ->missing('invoices.0.notes')
                ->where('filters.period', '2026-11')
                ->where('summary.billed', 1500000)
                ->where('summary.outstanding', 1500000));
    }

    public function test_filter_status_properti_periode_dan_pencarian(): void
    {
        $propertyA2 = $this->inTenant($this->tenantA, fn () => Property::factory()->create());
        $one = $this->leaseIn($this->tenantA, $this->propertyA);
        $two = $this->leaseIn($this->tenantA, $propertyA2);
        $three = $this->leaseIn($this->tenantA, $this->propertyA);
        $this->invoiceIn($this->tenantA, $one);
        $overdue = $this->invoiceIn($this->tenantA, $two, ['status' => InvoiceStatus::Overdue, 'due_date' => '2026-11-03']);
        $this->invoiceIn($this->tenantA, $three, ['period' => '2026-09']);

        $count = fn (string $query) => $this->actingAs($this->ownerA)->get('/app/tagihan'.$query)->assertOk()->viewData('page')['props']['invoices'];

        $this->assertCount(2, $count(''));                                 // bulan berjalan
        $this->assertCount(3, $count('?period='));                         // semua periode
        $this->assertCount(1, $count('?period=2026-09'));
        $this->assertCount(1, $count('?status=overdue'));
        $this->assertCount(1, $count('?property_id='.$propertyA2->id));
        $this->assertCount(1, $count('?q='.urlencode($overdue->number)));
        $this->assertCount(1, $count('?period=&q='.urlencode($three->primaryResident?->name ?? $this->inTenant($this->tenantA, fn () => $three->primaryResident->name))));
        $this->assertCount(0, $count('?q=tidak-ada-yang-cocok'));
    }

    public function test_filter_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->ownerA)->get('/app/tagihan?status=ngawur')->assertSessionHasErrors('status');
        $this->actingAs($this->ownerA)->get('/app/tagihan?period=November')->assertSessionHasErrors('period');
    }

    // Izin

    public function test_staf_tanpa_izin_keuangan_ditolak_dan_dengan_izin_hanya_melihat_dan_membuat_manual(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);
        $invoice = $this->invoiceIn($this->tenantA, $lease);

        $this->actingAs($this->staffNoFinance)->get('/app/tagihan')->assertForbidden();
        $this->actingAs($this->staffNoFinance)->get("/app/tagihan/{$invoice->id}")->assertForbidden();
        $this->actingAs($this->staffNoFinance)->get('/app/tagihan/baru')->assertForbidden();

        $this->actingAs($this->staffFinance)->get('/app/tagihan')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('invoices.0.can.adjust', false)->where('invoices.0.can.void', false));
        $this->actingAs($this->staffFinance)->get("/app/tagihan/{$invoice->id}")->assertOk();
        $this->actingAs($this->staffFinance)->get('/app/tagihan/baru')->assertOk();

        $this->actingAs($this->staffFinance)->post("/app/tagihan/{$invoice->id}/penyesuaian", ['amount' => -1000, 'reason' => 'Uji'])->assertForbidden();
        $this->actingAs($this->staffFinance)->post("/app/tagihan/{$invoice->id}/batal", ['reason' => 'Uji'])->assertForbidden();

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => InvoiceAdjustment::query()->count()));
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/app/tagihan')->assertRedirect(route('login'));
        $this->post('/app/tagihan/1/batal', ['reason' => 'x'])->assertRedirect(route('login'));
    }

    // Isolasi tenant lewat URL dan UI

    public function test_tenant_lain_tidak_bisa_membaca_atau_mengubah_tagihan(): void
    {
        $leaseA = $this->leaseIn($this->tenantA, $this->propertyA);
        $invoiceA = $this->invoiceIn($this->tenantA, $leaseA);
        $leaseB = $this->leaseIn($this->tenantB, $this->propertyB);
        $this->invoiceIn($this->tenantB, $leaseB, ['number' => 'INV-2026-11-7777']);

        // Daftar B hanya berisi tagihan B.
        $this->actingAs($this->ownerB)->get('/app/tagihan')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('invoices', 1)->where('invoices.0.number', 'INV-2026-11-7777'));

        // URL langsung ke tagihan A: 404 (di luar scope tenant B).
        $this->actingAs($this->ownerB)->get("/app/tagihan/{$invoiceA->id}")->assertNotFound();
        $this->actingAs($this->ownerB)->post("/app/tagihan/{$invoiceA->id}/penyesuaian", ['amount' => -1000, 'reason' => 'Serang'])->assertNotFound();
        $this->actingAs($this->ownerB)->post("/app/tagihan/{$invoiceA->id}/batal", ['reason' => 'Serang'])->assertNotFound();

        // Filter properti milik tenant A dari sisi B tidak mengembalikan apa pun.
        $this->actingAs($this->ownerB)->get('/app/tagihan?property_id='.$this->propertyA->id.'&period=')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('invoices', 0));

        $fresh = $invoiceA->fresh();
        $this->assertSame(InvoiceStatus::Issued, $fresh->status);
        $this->assertSame(1500000, $fresh->total);
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => InvoiceAdjustment::query()->count()));
    }

    public function test_tagihan_manual_untuk_kontrak_tenant_lain_ditolak(): void
    {
        $leaseA = $this->leaseIn($this->tenantA, $this->propertyA);

        $this->actingAs($this->ownerB)->post('/app/tagihan', [
            'lease_id' => $leaseA->id,
            'due_date' => '2026-11-30',
            'items' => [['type' => 'other', 'description' => 'Serang', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertSessionHasErrors('lease_id');

        $this->assertSame(0, Invoice::query()->withoutGlobalScopes()->count());
    }

    public function test_form_tagihan_manual_hanya_menawarkan_kontrak_tenant_sendiri(): void
    {
        $leaseA = $this->leaseIn($this->tenantA, $this->propertyA);
        $this->leaseIn($this->tenantB, $this->propertyB);

        $this->actingAs($this->ownerA)->get('/app/tagihan/baru?lease_id='.$leaseA->id)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Invoices/Create')
                ->has('leases', 1)
                ->where('leases.0.id', $leaseA->id)
                ->where('defaults.lease_id', $leaseA->id));
    }

    // Detail, penyesuaian, pembatalan

    public function test_detail_tagihan_memuat_item_penyesuaian_dan_tanpa_kolom_terlarang(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);
        $invoice = $this->invoiceIn($this->tenantA, $lease);

        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/penyesuaian", ['amount' => -250000, 'reason' => 'Diskon promo'])
            ->assertRedirect()->assertSessionHas('success');

        $this->actingAs($this->ownerA)->get("/app/tagihan/{$invoice->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Invoices/Show')
                ->where('invoice.number', $invoice->number)
                ->where('invoice.adjustment_total', -250000)
                ->where('invoice.total', 1250000)
                ->where('invoice.adjustments.0.reason', 'Diskon promo')
                ->where('invoice.adjustments.0.created_by_name', $this->ownerA->name)
                ->where('invoice.can_void_now', true)
                ->missing('invoice.tenant_id')
                ->missing('invoice.adjustments.0.tenant_id')
                ->missing('invoice.adjustments.0.created_by'));
    }

    public function test_penyesuaian_tanpa_alasan_atau_nol_ditolak_dengan_pesan_indonesia(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);
        $invoice = $this->invoiceIn($this->tenantA, $lease);

        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/penyesuaian", ['amount' => -1000, 'reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Alasan penyesuaian wajib diisi.']);
        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/penyesuaian", ['amount' => 0, 'reason' => 'Nol'])
            ->assertSessionHasErrors(['amount' => 'Jumlah penyesuaian tidak boleh nol.']);
        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/penyesuaian", ['amount' => -99999999, 'reason' => 'Besar'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1500000, $invoice->fresh()->total);
    }

    public function test_pembatalan_tagihan_dan_penolakan_untuk_tagihan_lunas(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);
        $open = $this->invoiceIn($this->tenantA, $lease);
        $paid = $this->invoiceIn($this->tenantA, $lease, ['period' => '2026-10', 'status' => InvoiceStatus::Paid, 'paid_total' => 1500000]);

        $this->actingAs($this->ownerA)->post("/app/tagihan/{$open->id}/batal", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->ownerA)->post("/app/tagihan/{$open->id}/batal", ['reason' => 'Salah terbit'])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->ownerA)->post("/app/tagihan/{$paid->id}/batal", ['reason' => 'Coba'])->assertSessionHasErrors('invoice');

        $this->assertSame(InvoiceStatus::Void, $open->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(2, Invoice::query()->withoutGlobalScopes()->count()); // tidak ada yang dihapus
    }

    // Tagihan manual

    public function test_tagihan_manual_dibuat_lewat_form(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);

        $response = $this->actingAs($this->ownerA)->post('/app/tagihan', [
            'lease_id' => $lease->id,
            'due_date' => '2026-11-20',
            'notes' => 'Ganti kunci',
            'items' => [
                ['type' => 'other', 'description' => 'Kunci baru', 'quantity' => 2, 'unit_price' => 75000],
            ],
        ]);

        $invoice = Invoice::query()->withoutGlobalScopes()->sole();
        $response->assertRedirect(route('owner.invoices.show', $invoice));
        $this->assertSame(150000, $invoice->total);
        $this->assertSame('INV-2026-11-0001', $invoice->number);
        $this->assertSame($this->ownerA->id, $invoice->created_by);
        $this->assertSame($this->tenantA->id, $invoice->tenant_id);
    }

    public function test_tagihan_manual_validasi_dalam_bahasa_indonesia(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);

        $this->actingAs($this->ownerA)->post('/app/tagihan', ['lease_id' => $lease->id, 'due_date' => '2026-11-20', 'items' => []])
            ->assertSessionHasErrors(['items' => 'Tambahkan minimal satu item tagihan.']);
        $this->actingAs($this->ownerA)->post('/app/tagihan', [
            'lease_id' => $lease->id, 'due_date' => '2026-11-20',
            'items' => [['type' => 'other', 'description' => '', 'quantity' => 1, 'unit_price' => 1.5]],
        ])->assertSessionHasErrors(['items.0.description', 'items.0.unit_price']);
        $this->actingAs($this->ownerA)->post('/app/tagihan', [
            'lease_id' => $lease->id, 'due_date' => '2026-11-01',
            'items' => [['type' => 'other', 'description' => 'X', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertSessionHasErrors('due_date');

        $this->assertSame(0, Invoice::query()->withoutGlobalScopes()->count());
    }

    // Pengaturan penagihan

    public function test_pengaturan_penagihan_dibaca_dan_disimpan_pemilik(): void
    {
        $this->actingAs($this->ownerA)->get('/app/pengaturan/penagihan')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Settings/Billing')
                ->where('settings.invoice_prefix', 'INV')
                ->where('settings.issue_day', 1)
                ->missing('settings.tenant_id')
                ->missing('settings.late_fee_type'));

        $this->actingAs($this->ownerA)->patch('/app/pengaturan/penagihan', [
            'invoice_prefix' => 'KOS', 'issue_day' => 3, 'due_day' => 12, 'prorate_first_month' => false, 'prorate_last_month' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $settings = $this->inTenant($this->tenantA, fn () => BillingSetting::current());
        $this->assertSame('KOS', $settings->invoice_prefix);
        $this->assertSame(3, $settings->issue_day);
        $this->assertFalse($settings->prorate_first_month);

        // Tenant B tidak terpengaruh.
        $this->assertSame('INV', $this->inTenant($this->tenantB, fn () => BillingSetting::current()->invoice_prefix));
    }

    public function test_pengaturan_penagihan_validasi_dan_izin(): void
    {
        $valid = ['invoice_prefix' => 'INV', 'issue_day' => 1, 'due_day' => 5, 'prorate_first_month' => true, 'prorate_last_month' => true];

        $this->actingAs($this->ownerA)->patch('/app/pengaturan/penagihan', ['invoice_prefix' => 'IN V!', 'issue_day' => 32, 'due_day' => 0] + $valid)
            ->assertSessionHasErrors(['invoice_prefix', 'issue_day', 'due_day']);

        $this->actingAs($this->staffNoFinance)->get('/app/pengaturan/penagihan')->assertForbidden();
        $this->actingAs($this->staffNoFinance)->patch('/app/pengaturan/penagihan', $valid)->assertForbidden();
    }
}
