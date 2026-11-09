<?php

namespace Tests\Feature\Reports;

use App\Actions\RecordPayment;
use App\Actions\VoidPayment;
use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\ArrearsReport;
use App\Services\Reports\ExpenseReport;
use App\Services\Reports\IncomeReport;
use App\Services\Reports\MonthlyCashflowReport;
use App\Services\Reports\OccupancyReport;
use App\Services\Reports\ReportPeriod;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Aturan angka laporan: pemasukan, tunggakan, okupansi, rekap. Semua dijalankan di dalam tenant A. */
class ReportQueriesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->owner()->create();
        TenantContext::instance()->set($this->tenant);
        $this->property = Property::factory()->create(['name' => 'Griya A']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function leaseFor(?Property $property = null, array $attributes = []): Lease
    {
        $property ??= $this->property;
        $room = Room::factory()->create(['property_id' => $property->id]);
        $resident = Resident::factory()->create();

        return Lease::factory()->create($attributes + [
            'property_id' => $property->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id,
        ]);
    }

    private function invoiceFor(Lease $lease, array $attributes = []): Invoice
    {
        return Invoice::factory()->forLease($lease)->create($attributes + ['period' => null]);
    }

    private function pay(Invoice $invoice, string $paidOn, ?int $amount = null): Payment
    {
        return app(RecordPayment::class)->handle(
            Resident::query()->findOrFail($invoice->resident_id),
            $amount ?? $invoice->total,
            CarbonImmutable::parse($paidOn),
            PaymentMethod::Cash,
            [$invoice->id => $amount ?? $invoice->total],
            $this->owner,
        );
    }

    // Pemasukan

    public function test_pemasukan_hanya_menghitung_pembayaran_aktif_pada_paid_on_di_dalam_periode(): void
    {
        $inside = $this->invoiceFor($this->leaseFor(), ['total' => 1000000, 'subtotal' => 1000000]);
        $first = $this->invoiceFor($this->leaseFor(), ['total' => 700000, 'subtotal' => 700000]);
        $last = $this->invoiceFor($this->leaseFor(), ['total' => 300000, 'subtotal' => 300000]);
        $before = $this->invoiceFor($this->leaseFor(), ['total' => 500000, 'subtotal' => 500000]);
        $after = $this->invoiceFor($this->leaseFor(), ['total' => 400000, 'subtotal' => 400000]);
        $voided = $this->invoiceFor($this->leaseFor(), ['total' => 900000, 'subtotal' => 900000]);

        $this->pay($inside, '2026-10-15');
        $this->pay($first, '2026-10-01');
        $this->pay($last, '2026-10-31');
        $this->pay($before, '2026-09-30');
        $this->pay($after, '2026-11-01');
        app(VoidPayment::class)->handle($this->pay($voided, '2026-10-20'), 'Salah catat', $this->owner);

        $period = ReportPeriod::month('2026-10');
        $report = app(IncomeReport::class);

        $this->assertSame(2000000, $report->total($period));
        $this->assertSame(3, $report->count($period));
        $this->assertSame(3, $report->rows($period)->count());
        $this->assertSame(0, $report->total(ReportPeriod::month('2026-08')));
    }

    public function test_pemasukan_dipisah_per_properti_lewat_tagihan_yang_dibayar(): void
    {
        $other = Property::factory()->create(['name' => 'Griya B']);
        $invoiceA = $this->invoiceFor($this->leaseFor($this->property), ['total' => 1000000, 'subtotal' => 1000000]);
        $invoiceB = $this->invoiceFor($this->leaseFor($other), ['total' => 600000, 'subtotal' => 600000]);
        $this->pay($invoiceA, '2026-10-10');
        $this->pay($invoiceB, '2026-10-11');

        $report = app(IncomeReport::class);
        $period = ReportPeriod::month('2026-10');

        $this->assertSame(1600000, $report->total($period));
        $this->assertSame(1000000, $report->total($period, $this->property->id));
        $this->assertSame(600000, $report->total($period, $other->id));
    }

    public function test_pemasukan_satu_pembayaran_untuk_dua_properti_terbagi_menurut_tagihan(): void
    {
        $other = Property::factory()->create();
        $resident = Resident::factory()->create();
        $leaseA = $this->leaseFor($this->property, ['primary_resident_id' => $resident->id]);
        $leaseB = $this->leaseFor($other, ['primary_resident_id' => $resident->id]);
        $invoiceA = $this->invoiceFor($leaseA, ['total' => 800000, 'subtotal' => 800000]);
        $invoiceB = $this->invoiceFor($leaseB, ['total' => 200000, 'subtotal' => 200000]);

        app(RecordPayment::class)->handle(
            $resident, 1000000, CarbonImmutable::parse('2026-10-12'), PaymentMethod::Cash,
            [$invoiceA->id => 800000, $invoiceB->id => 200000], $this->owner,
        );

        $report = app(IncomeReport::class);
        $period = ReportPeriod::month('2026-10');

        $this->assertSame(1000000, $report->total($period));
        $this->assertSame(800000, $report->total($period, $this->property->id));
        $this->assertSame(200000, $report->total($period, $other->id));
        $this->assertSame(1, $report->count($period));
    }

    // Tunggakan

    public function test_umur_tunggakan_benar_di_batas_7_dan_8_serta_30_dan_31_hari(): void
    {
        // Hari ini (tanggal tenant) 2026-11-09.
        $today = CarbonImmutable::parse('2026-11-09');
        $cases = [1 => 'd1_7', 7 => 'd1_7', 8 => 'd8_30', 30 => 'd8_30', 31 => 'd31_plus', 90 => 'd31_plus'];

        foreach ($cases as $daysLate => $bucket) {
            $this->invoiceFor($this->leaseFor(), [
                'due_date' => $today->subDays($daysLate)->toDateString(),
                'total' => 1000000 + $daysLate, 'subtotal' => 1000000 + $daysLate,
            ]);
        }

        $rows = app(ArrearsReport::class)->rows($today);

        $this->assertCount(6, $rows);

        foreach ($cases as $daysLate => $bucket) {
            $row = $rows->firstWhere('days_late', $daysLate);
            $this->assertSame($bucket, $row['bucket'], "Terlambat $daysLate hari harus masuk $bucket");
        }

        $summary = app(ArrearsReport::class)->summary($today);
        $this->assertSame(2, $summary['buckets']['d1_7']['count']);
        $this->assertSame(2, $summary['buckets']['d8_30']['count']);
        $this->assertSame(2, $summary['buckets']['d31_plus']['count']);
    }

    public function test_tunggakan_tidak_memuat_tagihan_belum_jatuh_tempo_lunas_batal_atau_draf(): void
    {
        $today = CarbonImmutable::parse('2026-11-09');
        $lease = fn () => $this->leaseFor();

        $this->invoiceFor($lease(), ['due_date' => '2026-11-09']); // jatuh tempo hari ini: belum terlambat
        $this->invoiceFor($lease(), ['due_date' => '2026-11-20']);
        $this->invoiceFor($lease(), ['due_date' => '2026-10-01', 'status' => InvoiceStatus::Paid, 'paid_total' => 1500000]);
        $this->invoiceFor($lease(), ['due_date' => '2026-10-01', 'status' => InvoiceStatus::Void]);
        $this->invoiceFor($lease(), ['due_date' => '2026-10-01', 'status' => InvoiceStatus::Draft]);
        $this->invoiceFor($lease(), ['due_date' => '2026-10-01', 'status' => InvoiceStatus::Partial, 'paid_total' => 500000]);
        $this->invoiceFor($lease(), ['due_date' => '2026-10-20', 'status' => InvoiceStatus::Overdue]);

        $rows = app(ArrearsReport::class)->rows($today);

        $this->assertCount(2, $rows);
        $this->assertSame(1000000 + 1500000, $rows->sum('outstanding'));
        $this->assertSame(1500000, $rows->first()['outstanding'], 'Diurutkan dari sisa terbesar.');
    }

    // Okupansi

    public function test_okupansi_pada_tanggal_tertentu_mengikuti_riwayat_kontrak(): void
    {
        $roomA = Room::factory()->create(['property_id' => $this->property->id]);
        $roomB = Room::factory()->create(['property_id' => $this->property->id]);
        $roomC = Room::factory()->create(['property_id' => $this->property->id]);
        $resident = fn () => Resident::factory()->create()->id;

        // Kamar A: menghuni 1 Okt sampai check-out 20 Okt (zona Makassar, jadi 19 Okt 16:00 UTC = 20 Okt 00:00).
        Lease::factory()->ended()->create([
            'property_id' => $this->property->id, 'room_id' => $roomA->id, 'primary_resident_id' => $resident(),
            'start_date' => '2026-10-01', 'checked_out_at' => '2026-10-19 16:00:00', 'active_room_id' => null,
        ]);
        // Kamar B: mulai 10 Okt, masih aktif.
        Lease::factory()->create([
            'property_id' => $this->property->id, 'room_id' => $roomB->id, 'primary_resident_id' => $resident(),
            'start_date' => '2026-10-10', 'checked_out_at' => null,
        ]);
        // Kontrak batal di kamar C tidak pernah menghuni.
        Lease::factory()->create([
            'property_id' => $this->property->id, 'room_id' => $roomC->id, 'primary_resident_id' => $resident(),
            'start_date' => '2026-10-01', 'status' => LeaseStatus::Canceled, 'active_room_id' => null,
        ]);

        $report = app(OccupancyReport::class);

        $this->assertSame(1, $report->at(CarbonImmutable::parse('2026-10-05'))['occupied']);
        $this->assertSame(2, $report->at(CarbonImmutable::parse('2026-10-10'))['occupied']);
        $this->assertSame(2, $report->at(CarbonImmutable::parse('2026-10-19'))['occupied']);
        $this->assertSame(1, $report->at(CarbonImmutable::parse('2026-10-20'))['occupied'], 'Hari check-out dihitung kosong.');
        $this->assertSame(0, $report->at(CarbonImmutable::parse('2026-09-30'))['occupied']);

        $day = $report->at(CarbonImmutable::parse('2026-10-20'));
        $this->assertSame(3, $day['total']);
        $this->assertSame(2, $day['vacant']);
        $this->assertSame(33.3, $day['percent']);
    }

    public function test_okupansi_menghitung_kamar_asal_sebelum_pindah_dan_kamar_tujuan_sesudahnya(): void
    {
        $from = Room::factory()->create(['property_id' => $this->property->id]);
        $to = Room::factory()->create(['property_id' => $this->property->id]);
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id, 'room_id' => $to->id, 'primary_resident_id' => Resident::factory()->create()->id,
            'start_date' => '2026-10-01', 'checked_out_at' => null,
        ]);
        RoomTransfer::create([
            'lease_id' => $lease->id, 'from_room_id' => $from->id, 'to_room_id' => $to->id,
            'transferred_on' => '2026-10-15', 'reason' => 'Uji', 'created_by' => $this->owner->id,
        ]);

        $report = app(OccupancyReport::class);

        $before = $report->at(CarbonImmutable::parse('2026-10-14'));
        $this->assertSame(1, $before['occupied']);
        $after = $report->at(CarbonImmutable::parse('2026-10-15'));
        $this->assertSame(1, $after['occupied']);

        // Kamar asal terisi 1-14 Okt, kamar tujuan 15-31 Okt: pada 31 Okt (31 hari) masing-masing kosong 17 dan 14 hari.
        $month = $report->at(CarbonImmutable::parse('2026-10-31'));
        $this->assertSame(15.5, $month['avg_vacant_days']);
    }

    public function test_okupansi_dipisah_per_properti(): void
    {
        $other = Property::factory()->create(['name' => 'Griya B']);
        $this->leaseFor($this->property, ['start_date' => '2026-10-01']);
        Room::factory()->create(['property_id' => $this->property->id]);
        Room::factory()->create(['property_id' => $other->id]);

        $all = app(OccupancyReport::class)->at(CarbonImmutable::parse('2026-10-15'));
        $one = app(OccupancyReport::class)->at(CarbonImmutable::parse('2026-10-15'), $other->id);

        $this->assertSame(3, $all['total']);
        $this->assertSame(1, $all['occupied']);
        $this->assertCount(2, $all['properties']);
        $this->assertSame(1, $one['total']);
        $this->assertSame(0, $one['occupied']);
    }

    // Rekap bulanan

    public function test_saldo_bersih_sama_dengan_pemasukan_dikurangi_pengeluaran(): void
    {
        $this->pay($this->invoiceFor($this->leaseFor(), ['total' => 2000000, 'subtotal' => 2000000]), '2026-10-05');
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => 750000, 'spent_on' => '2026-10-07', 'created_by' => $this->owner->id]);
        Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => 3000000, 'spent_on' => '2026-09-07', 'created_by' => $this->owner->id]);
        $deleted = Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => 999000, 'spent_on' => '2026-10-08', 'created_by' => $this->owner->id]);
        $deleted->delete();

        $months = collect(app(MonthlyCashflowReport::class)->months('2026-10', 6));

        $this->assertCount(6, $months);
        $this->assertSame('2026-05', $months->first()['month']);
        $october = $months->last();
        $this->assertSame(2000000, $october['income']);
        $this->assertSame(750000, $october['expense']);
        $this->assertSame(1250000, $october['net']);
        $this->assertSame(-3000000, $months->firstWhere('month', '2026-09')['net'], 'Saldo boleh negatif.');
        $this->assertSame(750000, app(ExpenseReport::class)->total(ReportPeriod::month('2026-10')));
    }

    public function test_rekap_dengan_filter_properti_hanya_memuat_pengeluaran_properti_itu(): void
    {
        $other = Property::factory()->create();
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->create(['expense_category_id' => $category->id, 'property_id' => $this->property->id, 'amount' => 100000, 'spent_on' => '2026-10-07', 'created_by' => $this->owner->id]);
        Expense::factory()->create(['expense_category_id' => $category->id, 'property_id' => $other->id, 'amount' => 200000, 'spent_on' => '2026-10-07', 'created_by' => $this->owner->id]);
        Expense::factory()->create(['expense_category_id' => $category->id, 'property_id' => null, 'amount' => 400000, 'spent_on' => '2026-10-07', 'created_by' => $this->owner->id]);

        $report = app(ExpenseReport::class);
        $period = ReportPeriod::month('2026-10');

        $this->assertSame(700000, $report->total($period));
        $this->assertSame(100000, $report->total($period, $this->property->id));
        $this->assertSame(200000, $report->total($period, $other->id));
    }

    // Isolasi tenant

    public function test_laporan_tidak_membaca_data_tenant_lain(): void
    {
        $this->pay($this->invoiceFor($this->leaseFor(), ['total' => 1000000, 'subtotal' => 1000000]), '2026-10-05');
        $this->invoiceFor($this->leaseFor(), ['due_date' => '2026-10-01']);

        $tenantB = Tenant::factory()->create();

        TenantContext::instance()->runAs($tenantB, function () use ($tenantB) {
            $userB = User::factory()->forTenant($tenantB)->owner()->create();
            $property = Property::factory()->create();
            $room = Room::factory()->create(['property_id' => $property->id]);
            $lease = Lease::factory()->create([
                'property_id' => $property->id, 'room_id' => $room->id,
                'primary_resident_id' => Resident::factory()->create()->id, 'start_date' => '2026-10-01',
            ]);
            $unpaid = Invoice::factory()->forLease($lease)->create(['period' => null, 'due_date' => '2026-10-01']);
            $paid = Invoice::factory()->forLease($lease)->create(['period' => null]);
            app(RecordPayment::class)->handle(
                Resident::query()->findOrFail($paid->resident_id), $paid->total, CarbonImmutable::parse('2026-10-06'),
                PaymentMethod::Cash, [$paid->id => $paid->total], $userB,
            );
            Expense::factory()->create(['amount' => 123000, 'spent_on' => '2026-10-06', 'created_by' => $userB->id]);

            $this->assertSame(1500000, app(IncomeReport::class)->total(ReportPeriod::month('2026-10')));
            $this->assertSame($unpaid->id, app(ArrearsReport::class)->rows(CarbonImmutable::parse('2026-11-09'))->sole()['invoice_id']);
        });

        $this->assertSame(1000000, app(IncomeReport::class)->total(ReportPeriod::month('2026-10')));
        $this->assertSame(0, app(ExpenseReport::class)->total(ReportPeriod::month('2026-10')));
        $this->assertSame(1, app(ArrearsReport::class)->rows(CarbonImmutable::parse('2026-11-09'))->count());
        // Tenant A punya dua kontrak aktif (mulai 1 Nov); kontrak tenant B tidak ikut terhitung.
        $occupancy = app(OccupancyReport::class)->at(CarbonImmutable::parse('2026-11-09'));
        $this->assertSame(2, $occupancy['occupied']);
        $this->assertSame(2, $occupancy['total']);
    }
}
