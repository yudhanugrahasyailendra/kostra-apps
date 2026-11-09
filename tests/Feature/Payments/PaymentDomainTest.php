<?php

namespace Tests\Feature\Payments;

use App\Actions\ApprovePaymentProof;
use App\Actions\MarkOverdueInvoices;
use App\Actions\RecordPayment;
use App\Actions\RejectPaymentProof;
use App\Actions\SavePaymentAccount;
use App\Actions\SubmitPaymentProof;
use App\Actions\VoidInvoice;
use App\Actions\VoidPayment;
use App\Enums\AttachmentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Exports\TenantExportRegistry;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAllocation;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentDomainTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');
        Storage::fake('attachments');

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->owner()->create();
        $this->property = $this->inTenant(fn () => Property::factory()->create());
        $this->lease = $this->makeLease();
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

    private function makeLease(?Tenant $tenant = null, ?Property $property = null, ?Resident $resident = null): Lease
    {
        return $this->inTenant(function () use ($property, $resident) {
            $property ??= $this->property;
            $room = Room::factory()->create(['property_id' => $property->id]);
            $resident ??= Resident::factory()->create();

            return Lease::factory()->create([
                'property_id' => $property->id,
                'room_id' => $room->id,
                'primary_resident_id' => $resident->id,
                'start_date' => '2026-01-01',
                'end_date' => '2027-12-31',
                'rent_amount' => 1500000,
            ]);
        }, $tenant);
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(int $total = 1500000, array $attributes = [], ?Lease $lease = null, ?Tenant $tenant = null): Invoice
    {
        $lease ??= $this->lease;

        return $this->inTenant(fn () => Invoice::factory()->forLease($lease)->create($attributes + [
            'period' => null,
            'subtotal' => $total,
            'total' => $total,
        ]), $tenant);
    }

    /**
     * @param  array<int, int>  $allocations
     */
    private function pay(array $allocations, ?int $amount = null, ?Lease $lease = null, ?User $by = null, PaymentMethod $method = PaymentMethod::Cash, ?int $accountId = null, string $paidOn = '2026-11-09'): Payment
    {
        $lease ??= $this->lease;

        return $this->inTenant(fn () => app(RecordPayment::class)->handle(
            Resident::query()->findOrFail($lease->primary_resident_id),
            $amount ?? array_sum($allocations),
            CarbonImmutable::parse($paidOn),
            $method,
            $allocations,
            $by ?? $this->owner,
            $accountId,
        ));
    }

    private function fresh(Invoice $invoice): Invoice
    {
        return $this->inTenant(fn () => Invoice::query()->findOrFail($invoice->id));
    }

    private function assertRejected(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail('Seharusnya ValidationException untuk '.$key);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    // Pembayaran sebagian dan lunas (PBY-01, PBY-02)

    public function test_pembayaran_sebagian_membuat_status_partial_lalu_pelunasan_membuat_paid(): void
    {
        $invoice = $this->invoice(1500000, ['due_date' => '2026-11-20']);

        $this->pay([$invoice->id => 600000]);
        $partial = $this->fresh($invoice);

        $this->assertSame(InvoiceStatus::Partial, $partial->status);
        $this->assertSame(600000, $partial->paid_total);
        $this->assertSame(900000, $partial->outstanding());
        $this->assertNull($partial->paid_at);

        $this->pay([$invoice->id => 900000]);
        $paid = $this->fresh($invoice);

        $this->assertSame(InvoiceStatus::Paid, $paid->status);
        $this->assertSame(1500000, $paid->paid_total);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame(0, $paid->outstanding());
    }

    public function test_satu_pembayaran_melunasi_dua_tagihan_lewat_alokasi(): void
    {
        $a = $this->invoice(1000000);
        $b = $this->invoice(500000);

        $payment = $this->pay([$a->id => 1000000, $b->id => 500000]);

        $this->assertSame(1500000, $payment->amount);
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($a)->status);
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($b)->status);
        $this->assertSame(2, $this->inTenant(fn () => PaymentAllocation::query()->where('payment_id', $payment->id)->count()));
    }

    public function test_alokasi_melebihi_jumlah_pembayaran_ditolak(): void
    {
        $a = $this->invoice(1000000);
        $b = $this->invoice(500000);

        $this->assertRejected(fn () => $this->pay([$a->id => 1000000, $b->id => 500000], amount: 1200000), 'amount');

        $this->assertSame(0, $this->inTenant(fn () => Payment::query()->count()));
        $this->assertSame(InvoiceStatus::Issued, $this->fresh($a)->status);
    }

    public function test_alokasi_melebihi_sisa_tagihan_ditolak(): void
    {
        $invoice = $this->invoice(1000000);
        $this->pay([$invoice->id => 400000]);

        $this->assertRejected(fn () => $this->pay([$invoice->id => 700000]), 'allocations');

        $this->assertSame(400000, $this->fresh($invoice)->paid_total);
        $this->assertSame(1, $this->inTenant(fn () => Payment::query()->count()));
    }

    public function test_kelebihan_bayar_ditolak_jumlah_harus_sama_dengan_alokasi(): void
    {
        $invoice = $this->invoice(1000000);

        $this->assertRejected(fn () => $this->pay([$invoice->id => 1000000], amount: 1200000), 'amount');
        $this->assertSame(0, $this->inTenant(fn () => Payment::query()->count()));
    }

    public function test_jumlah_nol_atau_negatif_dan_alokasi_kosong_ditolak(): void
    {
        $invoice = $this->invoice(1000000);

        $this->assertRejected(fn () => $this->pay([$invoice->id => 0], amount: 0), 'amount');
        $this->assertRejected(fn () => $this->pay([$invoice->id => -5], amount: -5), 'amount');
        $this->assertRejected(fn () => $this->pay([], amount: 1000), 'allocations');
    }

    public function test_tanggal_pembayaran_di_masa_depan_ditolak(): void
    {
        $invoice = $this->invoice(1000000);

        $this->assertRejected(fn () => $this->pay([$invoice->id => 1000000], paidOn: '2026-11-10'), 'paid_on');
    }

    public function test_tagihan_batal_draf_dan_lunas_tidak_menerima_pembayaran(): void
    {
        $void = $this->invoice(1000000, ['status' => InvoiceStatus::Void]);
        $draft = $this->invoice(1000000, ['status' => InvoiceStatus::Draft]);
        $paid = $this->invoice(1000000);
        $this->pay([$paid->id => 1000000]);

        foreach ([$void, $draft, $paid] as $invoice) {
            $this->assertRejected(fn () => $this->pay([$invoice->id => 1000]), 'allocations');
        }
    }

    public function test_tagihan_penghuni_lain_tidak_bisa_dialokasikan(): void
    {
        $other = $this->makeLease();
        $invoice = $this->invoice(1000000, lease: $other);

        // Pembayaran atas nama penghuni kontrak utama, tagihan milik penghuni lain.
        $this->assertRejected(fn () => $this->pay([$invoice->id => 1000000]), 'allocations');
    }

    public function test_pembayaran_mengambil_rekening_aktif_milik_tenant_saja(): void
    {
        $invoice = $this->invoice(1000000);
        $tenantB = Tenant::factory()->create();
        $foreign = $this->inTenant(fn () => PaymentAccount::factory()->create(), $tenantB);
        $inactive = $this->inTenant(fn () => PaymentAccount::factory()->create(['is_active' => false]));

        $this->assertRejected(fn () => $this->pay([$invoice->id => 1000000], method: PaymentMethod::Transfer, accountId: $foreign->id), 'payment_account_id');
        $this->assertRejected(fn () => $this->pay([$invoice->id => 1000000], method: PaymentMethod::Transfer, accountId: $inactive->id), 'payment_account_id');
    }

    public function test_pembayaran_mencatat_siapa_dan_kapan(): void
    {
        $invoice = $this->invoice(1000000);
        $staff = User::factory()->forTenant($this->tenant)->staff(['finance.view'])->create();

        $payment = $this->pay([$invoice->id => 1000000], by: $staff, paidOn: '2026-11-07');

        $this->assertSame($staff->id, $payment->recorded_by);
        $this->assertSame('2026-11-07', $payment->paid_on->toDateString());
        $this->assertSame(PaymentStatus::Active, $payment->status);
        $this->assertNotNull($payment->receipt_issued_at);
    }

    // Pembatalan (PBY-07)

    public function test_void_pembayaran_mengembalikan_status_tagihan(): void
    {
        $invoice = $this->invoice(1500000, ['due_date' => '2026-11-20']);
        $payment = $this->pay([$invoice->id => 1500000]);
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);

        $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Salah catat', $this->owner));

        $restored = $this->fresh($invoice);
        $this->assertSame(InvoiceStatus::Issued, $restored->status);
        $this->assertSame(0, $restored->paid_total);
        $this->assertNull($restored->paid_at);

        $payment = $this->inTenant(fn () => Payment::query()->findOrFail($payment->id));
        $this->assertSame(PaymentStatus::Voided, $payment->status);
        $this->assertSame('Salah catat', $payment->void_reason);
        $this->assertSame($this->owner->id, $payment->voided_by);
        $this->assertNotNull($payment->voided_at);
        // Riwayat tetap ada: pembayaran dan alokasinya tidak dihapus.
        $this->assertSame(1, $this->inTenant(fn () => PaymentAllocation::query()->where('payment_id', $payment->id)->count()));
    }

    public function test_void_pembayaran_sebagian_mengembalikan_ke_issued_dan_menyisakan_pembayaran_lain(): void
    {
        $invoice = $this->invoice(1500000, ['due_date' => '2026-11-20']);
        $first = $this->pay([$invoice->id => 500000]);
        $this->pay([$invoice->id => 300000]);

        $this->inTenant(fn () => app(VoidPayment::class)->handle($first, 'Dobel', $this->owner));

        $after = $this->fresh($invoice);
        $this->assertSame(300000, $after->paid_total);
        $this->assertSame(InvoiceStatus::Partial, $after->status);
    }

    public function test_void_pembayaran_tagihan_lewat_jatuh_tempo_kembali_menjadi_overdue(): void
    {
        $invoice = $this->invoice(1500000, ['due_date' => '2026-11-05']);
        $payment = $this->pay([$invoice->id => 1500000]);
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);

        $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Dana tidak masuk', $this->owner));

        $this->assertSame(InvoiceStatus::Overdue, $this->fresh($invoice)->status);
    }

    public function test_void_wajib_beralasan_dan_tidak_bisa_dua_kali(): void
    {
        $invoice = $this->invoice(1000000);
        $payment = $this->pay([$invoice->id => 1000000]);

        $this->assertRejected(fn () => $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, '   ', $this->owner)), 'reason');

        $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Koreksi', $this->owner));

        $this->assertRejected(fn () => $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Lagi', $this->owner)), 'payment');
    }

    public function test_void_tercatat_di_audit_log(): void
    {
        $invoice = $this->invoice(1000000);
        $payment = $this->pay([$invoice->id => 1000000]);

        $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Koreksi', $this->owner));

        $log = $this->inTenant(fn () => AuditLog::query()
            ->where('auditable_type', 'payment')->where('auditable_id', $payment->id)->where('event', 'updated')->latest('id')->first());

        $this->assertNotNull($log);
        $this->assertSame('voided', $log->new_values['status']);
        $this->assertSame('Koreksi', $log->new_values['void_reason']);
    }

    public function test_pembayaran_batal_bisa_dicatat_ulang_dan_tagihan_lunas_lagi(): void
    {
        $invoice = $this->invoice(1000000);
        $payment = $this->pay([$invoice->id => 1000000]);
        $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Salah', $this->owner));

        $this->pay([$invoice->id => 1000000]);

        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);
        $this->assertSame(1000000, $this->fresh($invoice)->paid_total);
    }

    public function test_tagihan_dengan_pembayaran_aktif_tidak_bisa_dibatalkan_tetapi_bisa_setelah_pembayaran_dibatalkan(): void
    {
        $invoice = $this->invoice(1000000);
        $payment = $this->pay([$invoice->id => 400000]);

        $this->assertRejected(fn () => $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Batal')), 'invoice');

        $this->inTenant(fn () => app(VoidPayment::class)->handle($payment, 'Koreksi', $this->owner));
        $voided = $this->inTenant(fn () => app(VoidInvoice::class)->handle($invoice, 'Batal'));

        $this->assertSame(InvoiceStatus::Void, $voided->status);
    }

    public function test_penjadwal_overdue_memperhitungkan_pembayaran_sebagian(): void
    {
        $invoice = $this->invoice(1000000, ['due_date' => '2026-11-05']);
        $this->pay([$invoice->id => 300000]);

        app(MarkOverdueInvoices::class)->handle($this->tenant);

        $this->assertSame(InvoiceStatus::Overdue, $this->fresh($invoice)->status);
        $this->assertSame(300000, $this->fresh($invoice)->paid_total);
    }

    // Pencatatan bersamaan

    public function test_pencatatan_berurutan_pada_tagihan_yang_sama_tidak_bisa_melewati_sisa(): void
    {
        $invoice = $this->invoice(1000000);

        $this->pay([$invoice->id => 700000]);

        // Pencatatan kedua membaca sisa tagihan yang sudah dikurangi, bukan angka lama.
        $this->assertRejected(fn () => $this->pay([$invoice->id => 700000]), 'allocations');

        $this->assertSame(700000, $this->fresh($invoice)->paid_total);
        $this->assertSame(700000, (int) $this->inTenant(fn () => PaymentAllocation::query()->where('invoice_id', $invoice->id)->sum('amount')));
    }

    public function test_pencatatan_membaca_sisa_dari_baris_terkunci_bukan_dari_objek_basi(): void
    {
        $invoice = $this->invoice(1000000);
        $stale = $this->fresh($invoice); // objek yang dimuat sebelum pembayaran lain

        $this->pay([$invoice->id => 800000]);

        $this->assertSame(0, $stale->paid_total);
        $this->assertRejected(fn () => $this->pay([$stale->id => 500000]), 'allocations');
        $this->assertSame(800000, $this->fresh($invoice)->paid_total);
    }

    // Kuitansi

    public function test_nomor_kuitansi_urut_unik_dan_tanpa_lubang_saat_gagal(): void
    {
        $a = $this->invoice(1000000);
        $b = $this->invoice(1000000);

        $first = $this->pay([$a->id => 1000000]);

        // Pencatatan gagal setelah nomor diambil tidak boleh menghabiskan nomor.
        $this->assertRejected(fn () => $this->pay([$a->id => 1]), 'allocations');

        $second = $this->pay([$b->id => 400000]);

        $this->assertSame('KWT-2026-11-0001', $first->receipt_number);
        $this->assertSame('KWT-2026-11-0002', $second->receipt_number);
        $this->assertSame(2, $this->inTenant(fn () => Payment::query()->distinct()->count('receipt_number')));
        $this->assertSame(2, $this->inTenant(fn () => NumberSequence::query()->where('key', 'receipt')->value('last_number')));
    }

    public function test_nomor_kuitansi_dihitung_per_tenant(): void
    {
        $tenantB = Tenant::factory()->create();
        $ownerB = User::factory()->forTenant($tenantB)->owner()->create();
        $propertyB = $this->inTenant(fn () => Property::factory()->create(), $tenantB);
        $leaseB = $this->makeLease($tenantB, $propertyB);
        $invoiceB = $this->invoice(1000000, lease: $leaseB, tenant: $tenantB);
        $invoiceA = $this->invoice(1000000);

        $a = $this->pay([$invoiceA->id => 1000000]);
        $b = $this->inTenant(fn () => app(RecordPayment::class)->handle(
            Resident::query()->findOrFail($leaseB->primary_resident_id), 1000000, CarbonImmutable::parse('2026-11-09'),
            PaymentMethod::Cash, [$invoiceB->id => 1000000], $ownerB,
        ), $tenantB);

        $this->assertSame('KWT-2026-11-0001', $a->receipt_number);
        $this->assertSame('KWT-2026-11-0001', $b->receipt_number);
    }

    // Bukti transfer (PBY-03, PBY-04)

    private function submitProof(Invoice $invoice, int $amount, ?User $by = null): PaymentProof
    {
        return $this->inTenant(fn () => app(SubmitPaymentProof::class)->handle(
            $invoice, $amount, '2026-11-08', 'Transfer dari BCA', UploadedFile::fake()->image('bukti.jpg'), $by ?? $this->owner,
        ));
    }

    public function test_bukti_masuk_antrean_pending_dengan_berkas_dan_belum_mengubah_tagihan(): void
    {
        $invoice = $this->invoice(1500000);

        $proof = $this->submitProof($invoice, 1500000);

        $this->assertSame(ProofStatus::Pending, $proof->status);
        $this->assertSame($invoice->resident_id, $proof->resident_id);
        $this->assertSame(0, $this->fresh($invoice)->paid_total);
        $this->assertSame(0, $this->inTenant(fn () => Payment::query()->count()));

        $attachment = $this->inTenant(fn () => $proof->attachments()->sole());
        $this->assertSame(AttachmentCategory::TransferProof, $attachment->category);
        $this->assertFalse($attachment->is_sensitive);
        Storage::disk('attachments')->assertExists($attachment->path);
    }

    public function test_bukti_untuk_tagihan_tertutup_atau_melebihi_sisa_ditolak(): void
    {
        $paid = $this->invoice(1000000);
        $this->pay([$paid->id => 1000000]);
        $void = $this->invoice(1000000, ['status' => InvoiceStatus::Void]);
        $open = $this->invoice(1000000);

        $this->assertRejected(fn () => $this->submitProof($this->fresh($paid), 1000), 'invoice');
        $this->assertRejected(fn () => $this->submitProof($void, 1000), 'invoice');
        $this->assertRejected(fn () => $this->submitProof($open, 1000001), 'amount');
        $this->assertRejected(fn () => $this->submitProof($open, 0), 'amount');
        $this->assertSame(0, $this->inTenant(fn () => PaymentProof::query()->count()));
    }

    public function test_persetujuan_bukti_membuat_pembayaran_menutup_tagihan_dan_menerbitkan_kuitansi(): void
    {
        $invoice = $this->invoice(1500000);
        $proof = $this->submitProof($invoice, 1500000);
        $account = $this->inTenant(fn () => PaymentAccount::factory()->create());

        $approved = $this->inTenant(fn () => app(ApprovePaymentProof::class)->handle($proof, $this->owner, paymentAccountId: $account->id));

        $this->assertSame(ProofStatus::Approved, $approved->status);
        $this->assertSame($this->owner->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertNotNull($approved->payment_id);

        $payment = $this->inTenant(fn () => Payment::query()->findOrFail($approved->payment_id));
        $this->assertSame(1500000, $payment->amount);
        $this->assertSame('2026-11-08', $payment->paid_on->toDateString());
        $this->assertSame(PaymentMethod::Transfer, $payment->method);
        $this->assertSame($account->id, $payment->payment_account_id);
        $this->assertNotNull($payment->receipt_number);

        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);
    }

    public function test_pemilik_boleh_mengoreksi_jumlah_saat_menyetujui(): void
    {
        $invoice = $this->invoice(1500000);
        $proof = $this->submitProof($invoice, 1500000);

        $this->inTenant(fn () => app(ApprovePaymentProof::class)->handle($proof, $this->owner, amount: 1000000));

        $this->assertSame(InvoiceStatus::Partial, $this->fresh($invoice)->status);
        $this->assertSame(1000000, $this->fresh($invoice)->paid_total);
    }

    public function test_persetujuan_gagal_membuat_bukti_tetap_pending_dan_tidak_ada_pembayaran(): void
    {
        $invoice = $this->invoice(1500000);
        $proof = $this->submitProof($invoice, 1500000);
        // Tagihan dilunasi lewat jalur lain sebelum bukti disetujui.
        $this->pay([$invoice->id => 1500000]);

        $this->assertRejected(fn () => $this->inTenant(fn () => app(ApprovePaymentProof::class)->handle($proof, $this->owner)), 'allocations');

        $this->assertSame(ProofStatus::Pending, $this->inTenant(fn () => PaymentProof::query()->findOrFail($proof->id))->status);
        $this->assertSame(1, $this->inTenant(fn () => Payment::query()->count()));
    }

    public function test_bukti_tidak_bisa_disetujui_dua_kali(): void
    {
        $invoice = $this->invoice(1500000);
        $proof = $this->submitProof($invoice, 500000);
        $this->inTenant(fn () => app(ApprovePaymentProof::class)->handle($proof, $this->owner));

        $this->assertRejected(fn () => $this->inTenant(fn () => app(ApprovePaymentProof::class)->handle($proof, $this->owner)), 'proof');
        $this->assertSame(500000, $this->fresh($invoice)->paid_total);
        $this->assertSame(1, $this->inTenant(fn () => Payment::query()->count()));
    }

    public function test_penolakan_wajib_beralasan_dan_tidak_mengubah_tagihan(): void
    {
        $invoice = $this->invoice(1500000);
        $proof = $this->submitProof($invoice, 1500000);

        $this->assertRejected(fn () => $this->inTenant(fn () => app(RejectPaymentProof::class)->handle($proof, '  ', $this->owner)), 'reason');
        $this->assertSame(ProofStatus::Pending, $this->inTenant(fn () => PaymentProof::query()->findOrFail($proof->id))->status);

        $rejected = $this->inTenant(fn () => app(RejectPaymentProof::class)->handle($proof, 'Nominal tidak sesuai mutasi', $this->owner));

        $this->assertSame(ProofStatus::Rejected, $rejected->status);
        $this->assertSame('Nominal tidak sesuai mutasi', $rejected->reject_reason);
        $this->assertSame($this->owner->id, $rejected->reviewed_by);
        $this->assertNull($rejected->payment_id);
        $this->assertSame(0, $this->fresh($invoice)->paid_total);
        $this->assertSame(0, $this->inTenant(fn () => Payment::query()->count()));

        $this->assertRejected(fn () => $this->inTenant(fn () => app(ApprovePaymentProof::class)->handle($proof, $this->owner)), 'proof');
    }

    public function test_bukti_pending_lebih_dari_tiga_hari_muncul_di_scope_pengingat(): void
    {
        $invoice = $this->invoice(1500000);
        $fresh = $this->submitProof($invoice, 100000);
        $old = $this->submitProof($invoice, 100000);
        $oldApproved = $this->submitProof($invoice, 100000);
        $this->inTenant(function () use ($old, $oldApproved) {
            PaymentProof::query()->whereKey([$old->id, $oldApproved->id])->update(['created_at' => now()->subDays(4)]);
            PaymentProof::query()->whereKey($oldApproved->id)->update(['status' => ProofStatus::Rejected->value]);
        });

        $ids = $this->inTenant(fn () => PaymentProof::query()->needingReminder()->pluck('id')->all());

        $this->assertSame([$old->id], $ids);
        $this->assertNotContains($fresh->id, $ids);
    }

    // Rekening (PBY-06)

    public function test_rekening_pertama_menjadi_bawaan_dan_hanya_satu_bawaan(): void
    {
        $save = fn (array $data, ?PaymentAccount $account = null) => $this->inTenant(fn () => app(SavePaymentAccount::class)->handle($data, $account));

        $first = $save(['type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '123', 'account_name' => 'Pemilik']);
        $second = $save(['type' => 'bank', 'bank_name' => 'BRI', 'account_number' => '456', 'account_name' => 'Pemilik']);
        $this->assertTrue($first->is_default);
        $this->assertFalse($second->is_default);

        $save(['is_default' => true], $second);

        $this->assertSame([$second->id], $this->inTenant(fn () => PaymentAccount::query()->where('is_default', true)->pluck('id')->all()));
    }

    public function test_rekening_nonaktif_tidak_boleh_menjadi_bawaan(): void
    {
        $account = $this->inTenant(fn () => PaymentAccount::factory()->create());

        $this->assertRejected(fn () => $this->inTenant(fn () => app(SavePaymentAccount::class)->handle(['is_default' => true, 'is_active' => false], $account)), 'is_default');
    }

    // Isolasi tenant

    public function test_model_pembayaran_tidak_terlihat_dari_tenant_lain(): void
    {
        $invoice = $this->invoice(1000000);
        $payment = $this->pay([$invoice->id => 400000]);
        $proof = $this->submitProof($invoice, 100000);
        $account = $this->inTenant(fn () => PaymentAccount::factory()->create());
        $tenantB = Tenant::factory()->create();

        $this->inTenant(function () use ($payment, $proof, $account) {
            $this->assertNull(Payment::query()->find($payment->id));
            $this->assertNull(PaymentProof::query()->find($proof->id));
            $this->assertNull(PaymentAccount::query()->find($account->id));
            $this->assertSame(0, PaymentAllocation::query()->count());
        }, $tenantB);
    }

    public function test_tenant_lain_tidak_bisa_mengalokasikan_ke_tagihan_tenant_ini(): void
    {
        $invoice = $this->invoice(1000000);
        $tenantB = Tenant::factory()->create();
        $ownerB = User::factory()->forTenant($tenantB)->owner()->create();
        $residentB = $this->inTenant(fn () => Resident::factory()->create(), $tenantB);

        $this->assertRejected(fn () => $this->inTenant(fn () => app(RecordPayment::class)->handle(
            $residentB, 1000000, CarbonImmutable::parse('2026-11-09'), PaymentMethod::Cash, [$invoice->id => 1000000], $ownerB,
        ), $tenantB), 'allocations');

        $this->assertSame(0, $this->fresh($invoice)->paid_total);
    }

    public function test_model_pembayaran_terdaftar_di_ekspor_tenant(): void
    {
        $registered = app(TenantExportRegistry::class)->all();

        foreach ([Payment::class, PaymentAllocation::class, PaymentProof::class, PaymentAccount::class] as $model) {
            $this->assertContains($model, $registered);
        }
    }
}
