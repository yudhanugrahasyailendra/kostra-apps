<?php

namespace Tests\Feature\Reports;

use App\Actions\RecordPayment;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    private User $staffNoFinance;

    private User $staffFinance;

    private Property $propertyA;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenantA = Tenant::factory()->create(['name' => 'Kos Mawar']);
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->staffNoFinance = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->staffFinance = User::factory()->forTenant($this->tenantA)->staff(['finance.view'])->create();
        $this->propertyA = $this->inTenant($this->tenantA, fn () => Property::factory()->create(['name' => 'Griya Mawar']));
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

    /** Dua kamar terisi, satu pembayaran November, satu tagihan menunggak 12 hari, satu pengeluaran. */
    private function seedTenantA(): void
    {
        $this->inTenant($this->tenantA, function () {
            $leases = collect([1, 2])->map(function () {
                $room = Room::factory()->create(['property_id' => $this->propertyA->id]);

                return Lease::factory()->create([
                    'property_id' => $this->propertyA->id, 'room_id' => $room->id,
                    'primary_resident_id' => Resident::factory()->create()->id, 'start_date' => '2026-10-01',
                    'end_date' => '2026-11-25',
                ]);
            });
            Room::factory()->create(['property_id' => $this->propertyA->id]);

            $paid = Invoice::factory()->forLease($leases[0])->create(['period' => null, 'total' => 1500000, 'subtotal' => 1500000]);
            app(RecordPayment::class)->handle(
                Resident::query()->findOrFail($paid->resident_id), 1500000, CarbonImmutable::parse('2026-11-03'),
                PaymentMethod::Transfer, [$paid->id => 1500000], $this->ownerA,
            );

            Invoice::factory()->forLease($leases[1])->create(['period' => null, 'due_date' => '2026-10-28', 'total' => 1800000, 'subtotal' => 1800000]);
            Expense::factory()->create(['amount' => 400000, 'spent_on' => '2026-11-04', 'created_by' => $this->ownerA->id]);
        });
    }

    /** @return array<int, list<mixed>> */
    private function readXlsx(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
            }
        }

        $reader->close();

        return $rows;
    }

    // Dashboard dan laporan memakai angka yang sama

    public function test_angka_dashboard_sama_dengan_laporan_periode_yang_sama(): void
    {
        $this->seedTenantA();

        $dashboard = $this->actingAs($this->ownerA)->get('/app')->assertOk()->viewData('page')['props']['summary'];
        $income = $this->actingAs($this->ownerA)->get('/app/laporan?type=pemasukan&period=2026-11')->viewData('page')['props']['table'];
        $arrears = $this->actingAs($this->ownerA)->get('/app/laporan?type=tunggakan&date=2026-11-09')->viewData('page')['props']['table'];
        $occupancy = $this->actingAs($this->ownerA)->get('/app/laporan?type=okupansi&date=2026-11-09')->viewData('page')['props']['table'];

        $this->assertSame(1500000, $dashboard['finance']['income']);
        $this->assertSame($income['totals']['amount'], $dashboard['finance']['income']);
        $this->assertSame(1800000, $dashboard['finance']['arrears_total']);
        $this->assertSame($arrears['totals']['outstanding'], $dashboard['finance']['arrears_total']);
        $this->assertSame(66.7, $dashboard['occupancy']['percent']);
        $this->assertSame($occupancy['totals']['percent'], $dashboard['occupancy']['percent']);
        $this->assertSame(2, $dashboard['expiring_leases']['count']);
        $this->assertSame('2026-11-25', $dashboard['expiring_leases']['items'][0]['end_date']);
    }

    public function test_dashboard_komponen_dan_props(): void
    {
        $this->seedTenantA();

        $this->actingAs($this->ownerA)->get('/app')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Dashboard')
                ->where('can.finance', true)
                ->has('summary.finance.arrears_buckets', 3)
                ->has('summary.expiring_leases.items.0', fn ($item) => $item->missing('tenant_id')->missing('phone')->etc()));
    }

    public function test_rekap_bulanan_di_layar_cocok_dengan_hitungan_manual(): void
    {
        $this->seedTenantA();

        $table = $this->actingAs($this->ownerA)->get('/app/laporan?type=rekap&period=2026-11')->viewData('page')['props']['table'];
        $november = collect($table['rows'])->firstWhere('month', '2026-11');

        $this->assertCount(6, $table['rows']);
        $this->assertSame(['month' => '2026-11', 'label' => 'November 2026', 'income' => 1500000, 'expense' => 400000, 'net' => 1100000], $november);
        $this->assertSame(1100000, $table['totals']['net']);
    }

    // Izin

    public function test_staf_tanpa_finance_view_ditolak_di_laporan_dan_tidak_melihat_keuangan_di_dashboard(): void
    {
        $this->seedTenantA();

        foreach (['/app/laporan', '/app/laporan?type=tunggakan', '/app/laporan/xlsx', '/app/laporan/pdf'] as $url) {
            $this->actingAs($this->staffNoFinance)->get($url)->assertForbidden();
        }

        $this->actingAs($this->staffNoFinance)->get('/app')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Dashboard')
                ->where('can.finance', false)
                ->where('summary.finance', null)
                ->has('summary.occupancy')
                ->has('summary.expiring_leases'));
    }

    public function test_staf_dengan_finance_view_boleh_membuka_laporan_dan_ekspor(): void
    {
        $this->seedTenantA();

        $this->actingAs($this->staffFinance)->get('/app/laporan?type=pemasukan&period=2026-11')->assertOk();
        $this->actingAs($this->staffFinance)->get('/app/laporan/pdf?type=pemasukan&period=2026-11')->assertOk();
        $this->actingAs($this->staffFinance)->get('/app')->assertInertia(fn (AssertableInertia $page) => $page->where('can.finance', true)->has('summary.finance'));
    }

    // Ekspor

    public function test_ekspor_excel_memuat_baris_yang_sama_dengan_layar_dan_metadata(): void
    {
        $this->seedTenantA();

        foreach (['pemasukan' => 'period=2026-11', 'tunggakan' => 'date=2026-11-09', 'pengeluaran' => 'period=2026-11', 'okupansi' => 'date=2026-11-09', 'rekap' => 'period=2026-11'] as $type => $param) {
            $screen = $this->actingAs($this->ownerA)->get("/app/laporan?type=$type&$param")->viewData('page')['props']['table'];
            $response = $this->actingAs($this->ownerA)->get("/app/laporan/xlsx?type=$type&$param")->assertOk();
            $rows = $this->readXlsx($response->baseResponse->getFile()->getPathname());

            $headerAt = collect($rows)->search(fn ($row) => $row === array_column($screen['columns'], 'label'));
            $this->assertNotFalse($headerAt, "Header kolom laporan $type tidak ditemukan di Excel.");

            $body = array_slice($rows, $headerAt + 1, count($screen['rows']));
            foreach ($screen['rows'] as $i => $row) {
                foreach ($screen['columns'] as $c => $column) {
                    $this->assertEquals($row[$column['key']] ?? '', $body[$i][$c], "Laporan $type baris $i kolom {$column['key']}");
                }
            }

            $totalsRow = $rows[$headerAt + 1 + count($screen['rows'])] ?? null;
            foreach ($screen['totals'] ?? [] as $key => $value) {
                $index = array_search($key, array_column($screen['columns'], 'key'), true);
                $this->assertEquals($value, $totalsRow[$index], "Total $key laporan $type");
            }

            $meta = collect($rows)->take($headerAt)->flatten()->all();
            $this->assertContains('Kos Mawar', $meta);
            $this->assertContains('Semua properti', $meta);
            $this->assertContains('Tanggal cetak', $meta);
        }
    }

    public function test_ekspor_excel_memuat_nama_properti_dan_periode_saat_difilter(): void
    {
        $this->seedTenantA();

        $response = $this->actingAs($this->ownerA)->get("/app/laporan/xlsx?type=pemasukan&period=2026-11&property_id={$this->propertyA->id}")->assertOk();
        $rows = $this->readXlsx($response->baseResponse->getFile()->getPathname());
        $meta = collect($rows)->take(6)->flatten()->all();

        $this->assertContains('Griya Mawar', $meta);
        $this->assertContains('November 2026', $meta);
        $this->assertStringContainsString('laporan-pemasukan-2026-11.xlsx', $response->headers->get('content-disposition'));
        $this->assertSame(1500000, collect($rows)->firstWhere(0, 'Total')[6] ?? null);
    }

    public function test_ekspor_pdf_terunduh(): void
    {
        $this->seedTenantA();

        $response = $this->actingAs($this->ownerA)->get('/app/laporan/pdf?type=tunggakan&date=2026-11-09')->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // Validasi dan isolasi

    public function test_parameter_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->ownerA)->get('/app/laporan?type=rahasia')->assertSessionHasErrors('type');
        $this->actingAs($this->ownerA)->get('/app/laporan?period=bulan-ini')->assertSessionHasErrors('period');
        $this->actingAs($this->ownerA)->get('/app/laporan?date=09-11-2026')->assertSessionHasErrors('date');
        $this->actingAs($this->ownerA)->get('/app/laporan/xlsx?type=rahasia')->assertSessionHasErrors('type');
    }

    public function test_laporan_dan_ekspor_tidak_membocorkan_data_tenant_lain(): void
    {
        $this->seedTenantA();
        $propertyB = $this->inTenant($this->tenantB, function () {
            $property = Property::factory()->create(['name' => 'Rahasia B']);
            $room = Room::factory()->create(['property_id' => $property->id]);
            $lease = Lease::factory()->create([
                'property_id' => $property->id, 'room_id' => $room->id,
                'primary_resident_id' => Resident::factory()->create(['name' => 'Penghuni Rahasia'])->id, 'start_date' => '2026-10-01',
            ]);
            $invoice = Invoice::factory()->forLease($lease)->create(['period' => null, 'due_date' => '2026-09-01', 'total' => 7000000, 'subtotal' => 7000000]);
            app(RecordPayment::class)->handle(
                Resident::query()->findOrFail($invoice->resident_id), 1000000, CarbonImmutable::parse('2026-11-04'),
                PaymentMethod::Cash, [$invoice->id => 1000000], $this->ownerB,
            );
            Expense::factory()->create(['amount' => 5000000, 'spent_on' => '2026-11-04', 'created_by' => $this->ownerB->id]);

            return $property;
        });

        $dashboard = $this->actingAs($this->ownerA)->get('/app')->viewData('page')['props']['summary'];
        $this->assertSame(1500000, $dashboard['finance']['income']);
        $this->assertSame(1800000, $dashboard['finance']['arrears_total']);
        $this->assertSame(3, $dashboard['occupancy']['total']);

        foreach (['pemasukan', 'tunggakan', 'pengeluaran', 'rekap', 'okupansi'] as $type) {
            $json = json_encode($this->actingAs($this->ownerA)->get("/app/laporan?type=$type&period=2026-11&date=2026-11-09")->viewData('page')['props']);
            $this->assertStringNotContainsString('Rahasia B', $json);
            $this->assertStringNotContainsString('Penghuni Rahasia', $json);
        }

        // Filter dengan properti tenant lain ditolak, bukan diam-diam menghasilkan laporan kosong.
        $this->actingAs($this->ownerA)->get("/app/laporan?type=pemasukan&property_id={$propertyB->id}")->assertSessionHasErrors('property_id');
        $this->actingAs($this->ownerA)->get("/app/laporan/xlsx?type=pemasukan&property_id={$propertyB->id}")->assertSessionHasErrors('property_id');
    }

    // Kinerja

    public function test_dashboard_dengan_100_kamar_dan_12_bulan_terbuka_kurang_dari_2_detik(): void
    {
        $this->inTenant($this->tenantA, function () {
            $now = now();
            $category = ExpenseCategory::factory()->create();
            $invoices = [];
            $payments = [];

            for ($i = 0; $i < 100; $i++) {
                $room = Room::factory()->create(['property_id' => $this->propertyA->id]);
                $resident = Resident::factory()->create();
                $lease = Lease::factory()->create([
                    'property_id' => $this->propertyA->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id,
                    'start_date' => '2025-11-01', 'end_date' => '2026-11-30',
                ]);

                for ($m = 0; $m < 12; $m++) {
                    $month = CarbonImmutable::parse('2025-12-01')->addMonths($m);
                    $paid = ! ($m === 10 && $i < 10); // 10 penghuni menunggak tagihan Okt (30 hari)
                    $invoices[] = [
                        'tenant_id' => $this->tenantA->id, 'property_id' => $this->propertyA->id, 'lease_id' => $lease->id,
                        'resident_id' => $resident->id, 'number' => sprintf('INV-%d-%02d-%04d', $month->year, $month->month, $i),
                        'type' => 'recurring', 'period' => $month->format('Y-m'), 'issue_date' => $month->toDateString(),
                        'due_date' => $month->addDays(9)->toDateString(), 'status' => $paid ? 'paid' : 'issued',
                        'subtotal' => 1500000, 'adjustment_total' => 0, 'total' => 1500000, 'paid_total' => $paid ? 1500000 : 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($invoices, 500) as $chunk) {
                Invoice::query()->insert($chunk);
            }

            $recorded = $this->ownerA->id;
            $invoiceRows = Invoice::query()->where('status', 'paid')->get(['id', 'resident_id', 'issue_date']);
            foreach ($invoiceRows as $n => $invoice) {
                $payments[] = [
                    'tenant_id' => $this->tenantA->id, 'resident_id' => $invoice->resident_id, 'amount' => 1500000,
                    'paid_on' => $invoice->issue_date->addDays(3)->toDateString(), 'method' => 'cash', 'status' => 'active',
                    'recorded_by' => $recorded, 'receipt_number' => 'KWT-'.$n, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($payments, 500) as $chunk) {
                Payment::query()->insert($chunk);
            }

            $allocations = [];
            $paymentIds = Payment::query()->orderBy('id')->pluck('id');
            foreach ($invoiceRows as $n => $invoice) {
                $allocations[] = [
                    'tenant_id' => $this->tenantA->id, 'payment_id' => $paymentIds[$n], 'invoice_id' => $invoice->id,
                    'amount' => 1500000, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($allocations, 500) as $chunk) {
                PaymentAllocation::query()->insert($chunk);
            }

            Expense::query()->insert(collect(range(1, 100))->map(fn ($n) => [
                'tenant_id' => $this->tenantA->id, 'expense_category_id' => $category->id, 'amount' => 100000,
                'spent_on' => CarbonImmutable::parse('2026-11-01')->subDays($n)->toDateString(), 'description' => 'Uji',
                'created_by' => $recorded, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });

        $started = microtime(true);
        $response = $this->actingAs($this->ownerA)->get('/app')->assertOk();
        $elapsed = microtime(true) - $started;

        $summary = $response->viewData('page')['props']['summary'];
        $this->assertSame(100, $summary['occupancy']['total']);
        $this->assertSame(100, $summary['occupancy']['occupied']);
        $this->assertSame(100 * 1500000, $summary['finance']['income'], 'Semua tagihan Nov dibayar 4 Nov.');
        $this->assertSame(10 * 1500000, $summary['finance']['arrears_total'], 'Sepuluh tagihan Okt belum dibayar.');
        $this->assertLessThan(2.0, $elapsed, 'Dashboard harus terbuka kurang dari 2 detik.');
    }
}
