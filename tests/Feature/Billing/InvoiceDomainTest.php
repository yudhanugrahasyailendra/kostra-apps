<?php

namespace Tests\Feature\Billing;

use App\Actions\AddInvoiceAdjustment;
use App\Actions\CheckInResident;
use App\Actions\CheckOutResident;
use App\Actions\CreateManualInvoice;
use App\Actions\GenerateInvoiceNumber;
use App\Actions\GenerateMonthlyInvoices;
use App\Actions\MarkOverdueInvoices;
use App\Actions\RecalculateInvoice;
use App\Actions\VoidInvoice;
use App\Contracts\OutstandingBalance;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\NumberSequence;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class InvoiceDomainTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        // 09 Nov 2026 pukul 18:00 WITA: tanggal yang sama di UTC maupun zona tenant.
        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->owner()->create();
        $this->property = $this->inTenant(fn () => Property::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(callable $callback, ?Tenant $tenant = null): mixed
    {
        return TenantContext::instance()->runAs($tenant ?? $this->tenant, $callback);
    }

    /** @param  array<string, mixed>  $attributes */
    private function lease(array $attributes = [], ?Tenant $tenant = null, ?Property $property = null): Lease
    {
        return $this->inTenant(function () use ($attributes, $property) {
            $property ??= $this->property;
            $room = Room::factory()->create(['property_id' => $property->id]);
            $resident = Resident::factory()->create();

            return Lease::factory()->create($attributes + [
                'property_id' => $property->id,
                'room_id' => $room->id,
                'primary_resident_id' => $resident->id,
                'start_date' => '2026-11-01',
                'end_date' => '2027-10-31',
                'rent_amount' => 1500000,
            ]);
        }, $tenant);
    }

    /** @return array{period: string, created: int, skipped: int} */
    private function generate(?Tenant $tenant = null, ?string $today = null): array
    {
        return app(GenerateMonthlyInvoices::class)->handle($tenant ?? $this->tenant, $today ? CarbonImmutable::parse($today) : null);
    }

    private function invoiceOf(Lease $lease, ?string $period = '2026-11'): Invoice
    {
        return $this->inTenant(fn () => Invoice::query()->where('lease_id', $lease->id)->where('period', $period)->firstOrFail());
    }

    private function settings(array $values): void
    {
        $this->inTenant(fn () => BillingSetting::current()->update($values));
    }

    // Penerbitan bulanan idempoten (TGH-01)

    public function test_generator_dua_kali_pada_hari_yang_sama_tidak_membuat_tagihan_ganda(): void
    {
        $lease = $this->lease();

        $first = $this->generate();
        $second = $this->generate();

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $this->inTenant(fn () => Invoice::query()->where('lease_id', $lease->id)->count()));
    }

    public function test_seratus_kontrak_menghasilkan_seratus_tagihan_dan_ulangan_nol(): void
    {
        foreach (range(1, 100) as $i) {
            $this->lease();
        }

        $this->assertSame(100, $this->generate()['created']);
        $this->assertSame(0, $this->generate()['created']);
        $this->assertSame(100, $this->inTenant(fn () => Invoice::query()->count()));
        $this->assertSame(100, $this->inTenant(fn () => Invoice::query()->distinct()->count('number')));
    }

    public function test_unique_lease_period_menolak_duplikat_di_database(): void
    {
        $lease = $this->lease();
        $this->generate();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inTenant(fn () => Invoice::factory()->forLease($lease)->create(['number' => 'INV-2026-11-9999']));
    }

    public function test_tagihan_terbit_dengan_item_sewa_dan_tanggal(): void
    {
        $this->settings(['due_day' => 20]);
        $lease = $this->lease(['rent_amount' => 1850000]);

        $this->generate();
        $invoice = $this->invoiceOf($lease);

        $this->assertSame(InvoiceType::Recurring, $invoice->type);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame($lease->primary_resident_id, $invoice->resident_id);
        $this->assertSame('2026-11-09', $invoice->issue_date->toDateString());
        $this->assertSame('2026-11-20', $invoice->due_date->toDateString());
        $this->assertSame('2026-11-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-11-30', $invoice->period_end->toDateString());
        $this->assertSame(1850000, $invoice->subtotal);
        $this->assertSame(1850000, $invoice->total);

        $item = $this->inTenant(fn () => $invoice->items()->sole());
        $this->assertSame(InvoiceItemType::Rent, $item->type);
        $this->assertSame(1850000, $item->amount);
        $this->assertStringContainsString('November 2026', $item->description);
    }

    public function test_due_day_kontrak_menimpa_pengaturan_dan_dipotong_ke_panjang_bulan(): void
    {
        $this->settings(['due_day' => 20]);
        $override = $this->lease(['due_day' => 25]);
        $this->generate();

        $this->assertSame('2026-11-25', $this->invoiceOf($override)->due_date->toDateString());

        // Februari 2027 hanya 28 hari: hari ke-31 dimundurkan ke 28.
        $late = $this->lease(['due_day' => 31, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
        $this->generate(today: '2027-02-10');

        $this->assertSame('2027-02-28', $this->invoiceOf($late, '2027-02')->due_date->toDateString());
    }

    public function test_jatuh_tempo_tidak_pernah_sebelum_tanggal_terbit(): void
    {
        $this->settings(['due_day' => 5]); // 5 Nov sudah lewat pada 9 Nov
        $lease = $this->lease();
        $this->generate();

        $this->assertSame('2026-11-09', $this->invoiceOf($lease)->due_date->toDateString());
    }

    public function test_tidak_terbit_sebelum_issue_day_dan_menyusul_setelahnya(): void
    {
        $this->settings(['issue_day' => 12]);
        $this->lease();

        $this->assertSame(0, $this->generate(today: '2026-11-11')['created']);
        $this->assertSame(1, $this->generate(today: '2026-11-12')['created']);
    }

    public function test_kontrak_yang_check_in_setelah_tanggal_terbit_mendapat_tagihan_pada_putaran_berikutnya(): void
    {
        $this->lease();
        $this->generate(today: '2026-11-02');

        $late = $this->lease(['start_date' => '2026-11-20']);
        $result = $this->generate(today: '2026-11-20');

        $this->assertSame(1, $result['created']);
        $this->assertSame(550000, $this->invoiceOf($late)->total); // 11 hari dari 30
    }

    public function test_kontrak_yang_tidak_berhak_ditagih_dilewati(): void
    {
        $future = $this->lease(['start_date' => '2026-12-01', 'end_date' => '2027-11-30']);
        $ended = $this->lease(['end_date' => '2026-10-31']);
        $finished = $this->lease()->forceFill(['status' => 'ended', 'active_room_id' => null]);
        $this->inTenant(fn () => $finished->save());
        $free = $this->lease(['rent_amount' => 0]);

        $this->assertSame(0, $this->generate()['created']);
        $this->assertSame(0, $this->inTenant(fn () => Invoice::query()->whereIn('lease_id', [$future->id, $ended->id, $finished->id, $free->id])->count()));
    }

    public function test_tenant_ditangguhkan_tidak_ditagih_oleh_perintah_penjadwal(): void
    {
        $active = $this->lease();
        $suspended = Tenant::factory()->suspended()->create();
        $suspendedProperty = $this->inTenant(fn () => Property::factory()->create(), $suspended);
        $other = $this->lease([], $suspended, $suspendedProperty);

        $this->artisan('invoices:generate')->assertSuccessful();

        $this->assertSame(1, $this->inTenant(fn () => Invoice::query()->where('lease_id', $active->id)->count()));
        $this->assertSame(0, $this->inTenant(fn () => Invoice::query()->where('lease_id', $other->id)->count(), $suspended));
    }

    public function test_hari_ini_mengikuti_zona_waktu_tenant(): void
    {
        // 31 Okt 20:00 UTC = 1 Nov 04:00 WITA: sudah periode November bagi tenant.
        Carbon::setTestNow('2026-10-31 20:00:00');
        $this->lease(['start_date' => '2026-10-01', 'end_date' => '2027-09-30']);

        $this->assertSame('2026-11', $this->generate()['period']);
    }

    // Nomor (TGH-02)

    public function test_nomor_berformat_dan_berurutan_per_tenant_per_bulan(): void
    {
        $a = $this->lease();
        $b = $this->lease();
        $this->generate();

        $numbers = [$this->invoiceOf($a)->number, $this->invoiceOf($b)->number];
        sort($numbers);
        $this->assertSame(['INV-2026-11-0001', 'INV-2026-11-0002'], $numbers);

        // Bulan lain mulai dari 0001 lagi.
        $this->inTenant(fn () => DB::transaction(function () {
            $this->assertSame('INV-2026-12-0001', app(GenerateInvoiceNumber::class)->handle('2026-12'));
            $this->assertSame('INV-2026-12-0002', app(GenerateInvoiceNumber::class)->handle('2026-12'));
        }));
    }

    public function test_nomor_tiap_tenant_berdiri_sendiri_dan_memakai_prefiks(): void
    {
        $other = Tenant::factory()->create();
        $otherProperty = $this->inTenant(fn () => Property::factory()->create(), $other);
        $this->inTenant(fn () => BillingSetting::current()->update(['invoice_prefix' => 'KOS']), $other);
        $mine = $this->lease();
        $theirs = $this->lease([], $other, $otherProperty);

        $this->generate();
        $this->generate($other);

        $this->assertSame('INV-2026-11-0001', $this->invoiceOf($mine)->number);
        $this->assertSame('KOS-2026-11-0001', $this->inTenant(fn () => Invoice::query()->where('lease_id', $theirs->id)->sole()->number, $other));
    }

    public function test_pembangkit_nomor_mengunci_baris_di_dalam_transaksi(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $this->inTenant(fn () => DB::transaction(function () {
            app(GenerateInvoiceNumber::class)->handle('2026-11');
            app(GenerateInvoiceNumber::class)->handle('2026-11');
        }));

        $locking = array_filter($queries, fn ($sql) => str_contains($sql, 'number_sequences') && str_contains($sql, 'for update'));
        $this->assertGreaterThanOrEqual(2, count($locking));
    }

    public function test_nomor_tidak_berlubang_bila_penerbitan_dibatalkan(): void
    {
        try {
            $this->inTenant(fn () => DB::transaction(function () {
                app(GenerateInvoiceNumber::class)->handle('2026-11');
                throw new RuntimeException('gagal setelah nomor diambil');
            }));
        } catch (RuntimeException) {
        }

        $this->inTenant(fn () => DB::transaction(function () {
            $this->assertSame('INV-2026-11-0001', app(GenerateInvoiceNumber::class)->handle('2026-11'));
        }));
    }

    public function test_urutan_nomor_unik_per_tenant_kunci_dan_periode(): void
    {
        $this->inTenant(fn () => NumberSequence::create(['key' => 'invoice', 'period' => '2026-11', 'last_number' => 1]));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inTenant(fn () => NumberSequence::create(['key' => 'invoice', 'period' => '2026-11', 'last_number' => 5]));
    }

    public function test_nomor_dokumen_tidak_ganda_per_tenant(): void
    {
        $lease = $this->lease();
        $this->inTenant(fn () => Invoice::factory()->forLease($lease)->manual()->create(['number' => 'INV-2026-11-0001']));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inTenant(fn () => Invoice::factory()->forLease($lease)->manual()->create(['number' => 'INV-2026-11-0001']));
    }

    // Pro rata

    public function test_rumus_pro_rata_membulatkan_ke_rupiah_tanpa_float(): void
    {
        $this->assertSame(1500000, GenerateMonthlyInvoices::prorate(3000000, 15, 30));
        $this->assertSame(333333, GenerateMonthlyInvoices::prorate(1000000, 10, 30));
        $this->assertSame(666667, GenerateMonthlyInvoices::prorate(1000000, 20, 30));
        $this->assertSame(3000000, GenerateMonthlyInvoices::prorate(3000000, 30, 30));
        $this->assertSame(2100000, GenerateMonthlyInvoices::prorate(3100000, 21, 31));
        // Harga maksimum kolom tetap muat di integer 64-bit.
        $this->assertSame(2000000000, GenerateMonthlyInvoices::prorate(4000000000, 15, 30));
    }

    public function test_pro_rata_bulan_pertama_di_bulan_30_hari(): void
    {
        $lease = $this->lease(['start_date' => '2026-11-16', 'rent_amount' => 3000000]);
        $this->generate();

        $invoice = $this->invoiceOf($lease);
        $this->assertSame(1500000, $invoice->total);
        $this->assertSame('2026-11-16', $invoice->period_start->toDateString());
        $this->assertStringContainsString('pro rata 15 hari', $this->inTenant(fn () => $invoice->items()->sole()->description));
    }

    public function test_pro_rata_bulan_pertama_di_bulan_31_hari(): void
    {
        $lease = $this->lease(['start_date' => '2026-10-11', 'rent_amount' => 3100000]);
        $this->generate(today: '2026-10-15');

        $this->assertSame(2100000, $this->invoiceOf($lease, '2026-10')->total);
    }

    public function test_pro_rata_bulan_pertama_di_bulan_28_hari(): void
    {
        $lease = $this->lease(['start_date' => '2027-02-15', 'end_date' => '2028-01-31', 'rent_amount' => 2800000]);
        $this->generate(today: '2027-02-20');

        $this->assertSame(1400000, $this->invoiceOf($lease, '2027-02')->total);
    }

    public function test_pro_rata_bulan_terakhir_saat_kontrak_berakhir_di_tengah_bulan(): void
    {
        $lease = $this->lease(['start_date' => '2026-01-01', 'end_date' => '2026-11-10', 'rent_amount' => 3000000]);
        $this->generate();

        $invoice = $this->invoiceOf($lease);
        $this->assertSame(1000000, $invoice->total);
        $this->assertSame('2026-11-10', $invoice->period_end->toDateString());
    }

    public function test_kontrak_yang_mulai_dan_berakhir_di_bulan_yang_sama(): void
    {
        $lease = $this->lease(['start_date' => '2026-11-10', 'end_date' => '2026-11-20', 'rent_amount' => 3000000]);
        $this->generate();

        $this->assertSame(1100000, $this->invoiceOf($lease)->total);
    }

    public function test_pro_rata_dimatikan_menagih_bulan_penuh(): void
    {
        $this->settings(['prorate_first_month' => false, 'prorate_last_month' => false]);
        $first = $this->lease(['start_date' => '2026-11-16', 'rent_amount' => 3000000]);
        $last = $this->lease(['start_date' => '2026-01-01', 'end_date' => '2026-11-10', 'rent_amount' => 3000000]);
        $this->generate();

        $this->assertSame(3000000, $this->invoiceOf($first)->total);
        $this->assertSame(3000000, $this->invoiceOf($last)->total);
    }

    public function test_hanya_pro_rata_pertama_aktif_tidak_memotong_bulan_terakhir(): void
    {
        $this->settings(['prorate_last_month' => false]);
        $lease = $this->lease(['start_date' => '2026-01-01', 'end_date' => '2026-11-10', 'rent_amount' => 3000000]);
        $this->generate();

        $this->assertSame(3000000, $this->invoiceOf($lease)->total);
    }

    // Perhitungan, penyesuaian, pembatalan (TGH-03, TGH-07)

    public function test_total_selalu_subtotal_ditambah_penyesuaian(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);

        $this->inTenant(function () use ($invoice) {
            InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'type' => InvoiceItemType::Service, 'description' => 'Kebersihan', 'unit_price' => 50000, 'amount' => 50000, 'sort_order' => 1]);
            app(AddInvoiceAdjustment::class)->handle($invoice, -100000, 'Diskon', $this->owner);
            app(AddInvoiceAdjustment::class)->handle($invoice, 30000, 'Biaya tambahan', $this->owner);
        });

        $fresh = $this->invoiceOf($lease);
        $this->assertSame(1550000, $fresh->subtotal);
        $this->assertSame(-70000, $fresh->adjustment_total);
        $this->assertSame($fresh->subtotal + $fresh->adjustment_total, $fresh->total);
        $this->assertSame(1480000, $fresh->total);
    }

    public function test_penyesuaian_wajib_beralasan_dan_tidak_nol(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);

        foreach ([['', -1000], ['   ', -1000], ['Diskon', 0]] as [$reason, $amount]) {
            try {
                $this->inTenant(fn () => app(AddInvoiceAdjustment::class)->handle($invoice, $amount, $reason, $this->owner));
                $this->fail('Seharusnya ditolak');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(0, $this->inTenant(fn () => InvoiceAdjustment::query()->count()));
    }

    public function test_penyesuaian_tidak_boleh_membuat_total_negatif(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);

        $this->expectException(ValidationException::class);

        $this->inTenant(fn () => app(AddInvoiceAdjustment::class)->handle($invoice, -1500001, 'Terlalu besar', $this->owner));
    }

    public function test_penyesuaian_masuk_audit_dan_item_asli_tidak_berubah(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);

        $adjustment = $this->inTenant(fn () => app(AddInvoiceAdjustment::class)->handle($invoice, -200000, 'Listrik mati', $this->owner));

        $this->assertSame($this->owner->id, $adjustment->created_by);
        $this->assertSame(1500000, $this->inTenant(fn () => $invoice->items()->sole()->amount));
        $this->assertSame(1500000, $this->invoiceOf($lease)->subtotal);
        $this->assertTrue($this->inTenant(fn () => AuditLog::query()
            ->where('auditable_type', 'invoice_adjustment')->where('auditable_id', $adjustment->id)->where('event', 'created')->exists()));
    }

    public function test_tagihan_batal_tidak_bisa_disesuaikan(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);
        $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Salah terbit'));

        $this->expectException(ValidationException::class);

        $this->inTenant(fn () => app(AddInvoiceAdjustment::class)->handle($invoice, -1000, 'Coba', $this->owner));
    }

    public function test_tagihan_lunas_tidak_bisa_dibatalkan(): void
    {
        $lease = $this->lease();
        $invoice = $this->inTenant(fn () => Invoice::factory()->forLease($lease)->status(InvoiceStatus::Paid)->create(['paid_total' => 1500000]));

        try {
            $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Coba batalkan'));
            $this->fail('Tagihan lunas tidak boleh dibatalkan');
        } catch (ValidationException) {
        }

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_tagihan_dengan_pembayaran_sebagian_tidak_bisa_dibatalkan(): void
    {
        $lease = $this->lease();
        $invoice = $this->inTenant(fn () => Invoice::factory()->forLease($lease)->status(InvoiceStatus::Partial)->create(['paid_total' => 500000]));

        $this->expectException(ValidationException::class);

        $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Coba batalkan'));
    }

    public function test_pembatalan_butuh_alasan_menyimpan_baris_dan_tidak_terbit_ulang(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);

        try {
            $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, '  '));
            $this->fail('Alasan wajib');
        } catch (ValidationException) {
        }

        $voided = $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Salah terbit'));

        $this->assertSame(InvoiceStatus::Void, $voided->status);
        $this->assertSame('Salah terbit', $voided->void_reason);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame(1, $this->inTenant(fn () => Invoice::query()->where('lease_id', $lease->id)->count()));

        // Penjadwal tidak menerbitkan ulang periode yang dibatalkan.
        $this->assertSame(0, $this->generate()['created']);
        $this->assertSame(1, $this->inTenant(fn () => Invoice::query()->where('lease_id', $lease->id)->count()));

        $this->expectException(ValidationException::class);
        $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Lagi'));
    }

    public function test_recalculate_tidak_mengubah_tagihan_batal(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);
        $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Salah terbit'));

        $after = $this->inTenant(fn () => app(RecalculateInvoice::class)->handle($invoice));

        $this->assertSame(InvoiceStatus::Void, $after->status);
        $this->assertSame(0, $after->outstanding());
    }

    // Status (TGH-05)

    public function test_status_dari_angka_dan_tanggal(): void
    {
        $today = CarbonImmutable::parse('2026-11-09');
        $future = CarbonImmutable::parse('2026-11-20');
        $past = CarbonImmutable::parse('2026-11-05');
        $status = fn (InvoiceStatus $current, int $total, int $paid, $due) => RecalculateInvoice::statusFor($current, $total, $paid, $due, $today);

        $this->assertSame(InvoiceStatus::Issued, $status(InvoiceStatus::Issued, 1000, 0, $future));
        $this->assertSame(InvoiceStatus::Partial, $status(InvoiceStatus::Issued, 1000, 400, $future));
        $this->assertSame(InvoiceStatus::Paid, $status(InvoiceStatus::Partial, 1000, 1000, $future));
        $this->assertSame(InvoiceStatus::Overdue, $status(InvoiceStatus::Issued, 1000, 0, $past));
        $this->assertSame(InvoiceStatus::Overdue, $status(InvoiceStatus::Partial, 1000, 400, $past));
        $this->assertSame(InvoiceStatus::Paid, $status(InvoiceStatus::Overdue, 1000, 1000, $past));
        $this->assertSame(InvoiceStatus::Issued, $status(InvoiceStatus::Issued, 1000, 0, $today)); // jatuh tempo hari ini belum lewat
        $this->assertSame(InvoiceStatus::Draft, $status(InvoiceStatus::Draft, 1000, 0, $past));
        $this->assertSame(InvoiceStatus::Void, $status(InvoiceStatus::Void, 1000, 0, $past));
    }

    public function test_status_overdue_otomatis_setelah_jatuh_tempo_dan_lunas_tidak_berubah(): void
    {
        $leases = array_map(fn () => $this->lease(), range(1, 6));
        $due = '2026-11-05';
        $make = fn (Lease $lease, InvoiceStatus $status, array $extra = [], string $due = '2026-11-05') => $this->inTenant(
            fn () => Invoice::factory()->forLease($lease)->status($status)->create(['due_date' => $due] + $extra)
        );

        $issued = $make($leases[0], InvoiceStatus::Issued);
        $partial = $make($leases[1], InvoiceStatus::Partial, ['paid_total' => 500000]);
        $paid = $make($leases[2], InvoiceStatus::Paid, ['paid_total' => 1500000]);
        $void = $make($leases[3], InvoiceStatus::Void);
        $notDue = $make($leases[4], InvoiceStatus::Issued, [], '2026-11-09');
        $draft = $make($leases[5], InvoiceStatus::Draft);

        $changed = app(MarkOverdueInvoices::class)->handle($this->tenant);

        $this->assertSame(2, $changed);
        $this->assertSame(InvoiceStatus::Overdue, $issued->fresh()->status);
        $this->assertSame(InvoiceStatus::Overdue, $partial->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(InvoiceStatus::Void, $void->fresh()->status);
        $this->assertSame(InvoiceStatus::Issued, $notDue->fresh()->status);
        $this->assertSame(InvoiceStatus::Draft, $draft->fresh()->status);
        $this->assertSame($due, $issued->fresh()->due_date->toDateString());
    }

    public function test_perintah_overdue_hanya_menyentuh_tenant_yang_aktif_dan_tidak_lintas_tenant(): void
    {
        $mine = $this->lease();
        $other = Tenant::factory()->create();
        $otherProperty = $this->inTenant(fn () => Property::factory()->create(), $other);
        $theirs = $this->lease([], $other, $otherProperty);

        $myInvoice = $this->inTenant(fn () => Invoice::factory()->forLease($mine)->create(['due_date' => '2026-11-05']));
        $theirInvoice = $this->inTenant(fn () => Invoice::factory()->forLease($theirs)->create(['due_date' => '2026-11-05']), $other);

        // Memproses tenant A tidak boleh mengubah tagihan tenant B.
        $this->assertSame(1, app(MarkOverdueInvoices::class)->handle($this->tenant));
        $this->assertSame(InvoiceStatus::Overdue, $myInvoice->fresh()->status);
        $this->assertSame(InvoiceStatus::Issued, $this->inTenant(fn () => Invoice::query()->findOrFail($theirInvoice->id)->status, $other));

        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        $this->assertSame(InvoiceStatus::Overdue, $this->inTenant(fn () => Invoice::query()->findOrFail($theirInvoice->id)->status, $other));
    }

    // Tagihan manual (TGH-04)

    public function test_tagihan_manual_menghitung_item_dan_boleh_lebih_dari_satu_per_kontrak(): void
    {
        $lease = $this->lease();
        $this->generate();

        $data = [
            'due_date' => '2026-11-30',
            'notes' => 'Ganti kunci',
            'items' => [
                ['type' => 'other', 'description' => 'Kunci baru', 'quantity' => 1, 'unit_price' => 75000],
                ['type' => 'utility', 'description' => 'Listrik tambahan', 'quantity' => 1.5, 'unit_price' => 100000],
            ],
        ];

        $first = $this->inTenant(fn () => app(CreateManualInvoice::class)->handle($lease, $data, $this->owner));
        $second = $this->inTenant(fn () => app(CreateManualInvoice::class)->handle($lease, $data, $this->owner));

        $this->assertSame(InvoiceType::Manual, $first->type);
        $this->assertNull($first->period);
        $this->assertSame(InvoiceStatus::Issued, $first->status);
        $this->assertSame(225000, $first->total);
        $this->assertSame($this->owner->id, $first->created_by);
        $this->assertNotSame($first->number, $second->number);
        $this->assertMatchesRegularExpression('/^INV-2026-11-\d{4}$/', $first->number);
        $this->assertSame(3, $this->inTenant(fn () => Invoice::query()->where('lease_id', $lease->id)->count()));
    }

    public function test_tagihan_manual_ditolak_tanpa_item_atau_dengan_jatuh_tempo_lampau(): void
    {
        $lease = $this->lease();

        foreach ([
            ['due_date' => '2026-11-30', 'items' => []],
            ['due_date' => '2026-11-08', 'items' => [['type' => 'other', 'description' => 'X', 'quantity' => 1, 'unit_price' => 1000]]],
        ] as $data) {
            try {
                $this->inTenant(fn () => app(CreateManualInvoice::class)->handle($lease, $data, $this->owner));
                $this->fail('Seharusnya ditolak');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(0, $this->inTenant(fn () => Invoice::query()->count()));
    }

    // Sisa tagihan dan check-out

    public function test_sisa_tagihan_menjumlah_tagihan_terbuka_milik_kontrak_itu_saja(): void
    {
        $lease = $this->lease();
        $other = $this->lease();

        $this->inTenant(function () use ($lease, $other) {
            Invoice::factory()->forLease($lease)->create(['period' => '2026-08', 'total' => 1500000]);
            Invoice::factory()->forLease($lease)->status(InvoiceStatus::Overdue)->create(['period' => '2026-09', 'total' => 1000000, 'paid_total' => 0]);
            Invoice::factory()->forLease($lease)->status(InvoiceStatus::Paid)->create(['period' => '2026-10', 'total' => 1500000, 'paid_total' => 1500000]);
            Invoice::factory()->forLease($lease)->status(InvoiceStatus::Void)->create(['period' => '2026-07', 'total' => 900000]);
            Invoice::factory()->forLease($lease)->status(InvoiceStatus::Draft)->create(['period' => '2026-06', 'total' => 700000]);
            Invoice::factory()->forLease($other)->create(['period' => '2026-08', 'total' => 800000]);
        });

        $balance = $this->inTenant(fn () => app(OutstandingBalance::class)->forLease($lease));

        $this->assertSame(2500000, $balance);
    }

    public function test_sisa_tagihan_nol_tanpa_tagihan(): void
    {
        $this->assertSame(0, $this->inTenant(fn () => app(OutstandingBalance::class)->forLease($this->lease())));
    }

    public function test_check_out_memotong_deposit_dengan_sisa_tagihan_dari_invoices(): void
    {
        $room = $this->inTenant(fn () => Room::factory()->create(['property_id' => $this->property->id]));
        $resident = $this->inTenant(fn () => Resident::factory()->create());
        $lease = $this->inTenant(fn () => app(CheckInResident::class)->handle([
            'room_id' => $room->id,
            'primary_resident_id' => $resident->id,
            'start_date' => '2026-11-01',
            'duration_months' => 12,
            'deposit_amount' => 1000000,
            'deposit_received_at' => '2026-11-01',
        ]));
        $this->inTenant(fn () => Invoice::factory()->forLease($lease)->create(['total' => 400000, 'paid_total' => 0]));

        $this->inTenant(fn () => app(CheckOutResident::class)->handle($lease, ['deduct_outstanding' => true]));

        $deposit = $this->inTenant(fn () => $lease->deposit()->firstOrFail());
        $this->assertSame(400000, $deposit->deduction_amount);
        $this->assertSame(600000, $deposit->refund_amount);
    }

    // Isolasi tenant pada tingkat model

    public function test_model_penagihan_tidak_terlihat_dari_tenant_lain(): void
    {
        $lease = $this->lease();
        $this->generate();
        $invoice = $this->invoiceOf($lease);
        $this->inTenant(fn () => app(AddInvoiceAdjustment::class)->handle($invoice, -1000, 'Uji', $this->owner));

        $other = Tenant::factory()->create();

        $this->inTenant(function () {
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, InvoiceItem::query()->count());
            $this->assertSame(0, InvoiceAdjustment::query()->count());
            $this->assertSame(0, NumberSequence::query()->count());
            $this->assertSame(0, BillingSetting::query()->count());
        }, $other);
    }

    public function test_pengaturan_penagihan_dibuat_dengan_nilai_bawaan_konfigurasi(): void
    {
        $settings = $this->inTenant(fn () => BillingSetting::current());

        $this->assertSame('INV', $settings->invoice_prefix);
        $this->assertSame(1, $settings->issue_day);
        $this->assertSame(5, $settings->due_day);
        $this->assertTrue($settings->prorate_first_month);
        $this->assertSame([-3, 0, 3], $settings->reminder_offsets);
        $this->assertSame($settings->id, $this->inTenant(fn () => BillingSetting::current()->id));
    }
}
