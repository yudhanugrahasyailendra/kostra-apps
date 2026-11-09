<?php

namespace Tests\Feature\Payments;

use App\Actions\RecordPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAccount;
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
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PaymentHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    private User $staffNoFinance;

    private User $staffFinance;

    private Property $propertyA;

    private Property $propertyB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');
        Storage::fake('attachments');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->staffNoFinance = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->staffFinance = User::factory()->forTenant($this->tenantA)->staff(['finance.view'])->create();
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
        return $this->inTenant($tenant, fn () => Invoice::factory()->forLease($lease)->create($attributes + ['period' => null]));
    }

    private function paymentIn(Tenant $tenant, User $by, Invoice $invoice, ?int $amount = null): Payment
    {
        return $this->inTenant($tenant, fn () => app(RecordPayment::class)->handle(
            Resident::query()->findOrFail($invoice->resident_id),
            $amount ?? $invoice->total,
            CarbonImmutable::parse('2026-11-09'),
            PaymentMethod::Cash,
            [$invoice->id => $amount ?? $invoice->total],
            $by,
        ));
    }

    private function proofIn(Tenant $tenant, Invoice $invoice): PaymentProof
    {
        return $this->inTenant($tenant, fn () => PaymentProof::factory()->create([
            'invoice_id' => $invoice->id, 'resident_id' => $invoice->resident_id, 'amount' => $invoice->total,
        ]));
    }

    /** @return array<string, mixed> */
    private function paymentBody(Invoice $invoice, array $overrides = []): array
    {
        return $overrides + [
            'resident_id' => $invoice->resident_id,
            'amount' => $invoice->total,
            'paid_on' => '2026-11-09',
            'method' => 'cash',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $invoice->total]],
        ];
    }

    // Catat pembayaran

    public function test_pemilik_mencatat_pembayaran_dan_diarahkan_ke_kuitansi(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $response = $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice));

        $payment = $this->inTenant($this->tenantA, fn () => Payment::query()->sole());
        $response->assertRedirect("/app/pembayaran/{$payment->id}/kuitansi");
        $this->assertSame(PaymentStatus::Active, $payment->status);
        $this->assertSame('KWT-2026-11-0001', $payment->receipt_number);
        $this->assertSame(InvoiceStatus::Paid, $this->inTenant($this->tenantA, fn () => Invoice::query()->find($invoice->id))->status);
    }

    public function test_staf_berizin_keuangan_boleh_mencatat_dan_membatalkan_staf_tanpa_izin_tidak(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $this->actingAs($this->staffNoFinance)->post('/app/pembayaran', $this->paymentBody($invoice))->assertForbidden();
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Payment::query()->count()));

        $this->actingAs($this->staffFinance)->post('/app/pembayaran', $this->paymentBody($invoice))->assertRedirect();
        $payment = $this->inTenant($this->tenantA, fn () => Payment::query()->sole());

        $this->actingAs($this->staffNoFinance)->post("/app/pembayaran/{$payment->id}/batal", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->staffFinance)->post("/app/pembayaran/{$payment->id}/batal", ['reason' => 'Salah catat'])->assertRedirect();

        $this->assertSame(PaymentStatus::Voided, $this->inTenant($this->tenantA, fn () => Payment::query()->find($payment->id))->status);
    }

    public function test_staf_tanpa_izin_keuangan_ditolak_di_semua_halaman_pembayaran(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $payment = $this->paymentIn($this->tenantA, $this->ownerA, $invoice, 100000);
        $proof = $this->proofIn($this->tenantA, $invoice);

        $this->actingAs($this->staffNoFinance);

        $this->get('/app/pembayaran')->assertForbidden();
        $this->get('/app/pembayaran/baru')->assertForbidden();
        $this->get("/app/pembayaran/{$payment->id}/kuitansi")->assertForbidden();
        $this->get("/app/pembayaran/{$payment->id}/kuitansi.pdf")->assertForbidden();
        $this->get('/app/bukti-transfer')->assertForbidden();
        $this->post("/app/bukti-transfer/{$proof->id}/setujui", ['method' => 'transfer'])->assertForbidden();
        $this->post("/app/bukti-transfer/{$proof->id}/tolak", ['reason' => 'x'])->assertForbidden();
        $this->post("/app/tagihan/{$invoice->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->image('b.jpg')])->assertForbidden();
    }

    public function test_pembatalan_wajib_alasan_dengan_pesan_indonesia(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $payment = $this->paymentIn($this->tenantA, $this->ownerA, $invoice);

        $this->actingAs($this->ownerA)->post("/app/pembayaran/{$payment->id}/batal", ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Alasan pembatalan wajib diisi.']);
    }

    public function test_pencatatan_melebihi_sisa_dan_kelebihan_bayar_menampilkan_galat_formulir(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, [
            'amount' => 2000000,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 2000000]],
        ]))->assertSessionHasErrors('allocations');

        $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, ['amount' => 1600000]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Payment::query()->count()));
    }

    public function test_validasi_input_pembayaran(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, ['method' => 'bitcoin']))
            ->assertSessionHasErrors('method');
        $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, ['amount' => 1500000.5]))
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, ['allocations' => []]))
            ->assertSessionHasErrors('allocations');
        $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, ['paid_on' => '2026-11-10']))
            ->assertSessionHasErrors('paid_on');
    }

    // Props aman

    public function test_halaman_tagihan_memuat_riwayat_pembayaran_tanpa_kolom_terlarang(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $this->paymentIn($this->tenantA, $this->ownerA, $invoice, 600000);
        $this->proofIn($this->tenantA, $invoice);

        $this->actingAs($this->ownerA)->get("/app/tagihan/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Invoices/Show')
                ->where('invoice.paid_total', 600000)
                ->where('invoice.outstanding', 900000)
                ->has('payments', 1)
                ->where('payments.0.amount', 600000)
                ->where('payments.0.can.void', true)
                ->missing('payments.0.tenant_id')
                ->missing('payments.0.recorded_by')
                ->missing('payments.0.resident_phone')
                ->has('proofs', 1)
                ->where('proofs.0.status', 'pending')
                ->missing('proofs.0.tenant_id')
                ->missing('proofs.0.path')
                ->where('can_pay', true)
                ->where('can_submit_proof', true));
    }

    public function test_staf_tanpa_izin_keuangan_tidak_melihat_tagihan(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $this->actingAs($this->staffNoFinance)->get("/app/tagihan/{$invoice->id}")->assertForbidden();
    }

    public function test_daftar_pembayaran_filter_dan_ringkasan(): void
    {
        $leaseOne = $this->leaseIn($this->tenantA, $this->propertyA);
        $leaseTwo = $this->leaseIn($this->tenantA, $this->propertyA);
        $one = $this->invoiceIn($this->tenantA, $leaseOne);
        $two = $this->invoiceIn($this->tenantA, $leaseTwo);
        $first = $this->paymentIn($this->tenantA, $this->ownerA, $one, 500000);
        $second = $this->paymentIn($this->tenantA, $this->ownerA, $two, 700000);
        $this->actingAs($this->ownerA)->post("/app/pembayaran/{$second->id}/batal", ['reason' => 'Salah']);

        $list = fn (string $query = '') => $this->actingAs($this->ownerA)->get('/app/pembayaran'.$query)->assertOk()->viewData('page')['props'];

        $props = $list();
        $this->assertCount(2, $props['payments']);
        $this->assertSame(500000, $props['summary']['received']);
        $this->assertSame(['all' => 2, 'active' => 1, 'voided' => 1], $props['counts']);
        $this->assertCount(1, $list('?status=voided')['payments']);
        $this->assertCount(1, $list('?q='.urlencode($first->receipt_number))['payments']);
        $this->assertCount(0, $list('?period=2026-09')['payments']);
        $this->actingAs($this->ownerA)->get('/app/pembayaran?status=ngawur')->assertSessionHasErrors('status');
    }

    public function test_halaman_catat_pembayaran_menyiapkan_tagihan_terbuka_penghuni(): void
    {
        $lease = $this->leaseIn($this->tenantA, $this->propertyA);
        $open = $this->invoiceIn($this->tenantA, $lease);
        $this->invoiceIn($this->tenantA, $lease, ['status' => InvoiceStatus::Paid, 'paid_total' => 1500000]);
        $this->inTenant($this->tenantA, fn () => PaymentAccount::factory()->create(['is_default' => true]));

        $this->actingAs($this->ownerA)->get("/app/pembayaran/baru?invoice_id={$open->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Payments/Create')
                ->has('residents', 1)
                ->where('resident_id', $open->resident_id)
                ->has('invoices', 1)
                ->where('invoices.0.outstanding', 1500000)
                ->where('defaults.invoice_id', $open->id)
                ->has('accounts', 1));
    }

    // Kuitansi

    public function test_kuitansi_dapat_diunduh_sebagai_pdf(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $payment = $this->paymentIn($this->tenantA, $this->ownerA, $invoice);

        $response = $this->actingAs($this->ownerA)->get("/app/pembayaran/{$payment->id}/kuitansi.pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($payment->receipt_number, $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_kuitansi_memuat_data_yang_diperlukan_tanpa_data_pribadi_berlebih(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $payment = $this->paymentIn($this->tenantA, $this->ownerA, $invoice);

        $this->actingAs($this->ownerA)->get("/app/pembayaran/{$payment->id}/kuitansi")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Payments/Receipt')
                ->where('receipt.number', $payment->receipt_number)
                ->where('receipt.amount', 1500000)
                ->where('receipt.status', 'active')
                ->has('receipt.lines', 1)
                ->where('receipt.lines.0.invoice_number', $invoice->number)
                ->missing('receipt.resident_phone')
                ->missing('receipt.tenant_id')
                ->where('can.void', true));
    }

    // Bukti transfer

    public function test_siklus_penuh_bukti_diunggah_disetujui_kuitansi_dan_tunggakan_berubah(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA), ['status' => InvoiceStatus::Overdue, 'due_date' => '2026-11-05']);

        $this->actingAs($this->staffFinance)->post("/app/tagihan/{$invoice->id}/bukti", [
            'amount' => 1500000,
            'paid_on' => '2026-11-08',
            'sender_note' => 'BCA a.n. Penghuni',
            'file' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect('/app/bukti-transfer');

        $queue = $this->actingAs($this->ownerA)->get('/app/bukti-transfer')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/PaymentProofs/Index')
                ->has('proofs', 1)
                ->where('proofs.0.status', 'pending')
                ->where('proofs.0.amount', 1500000)
                ->where('counts.pending', 1)
                ->missing('proofs.0.file.path')
                ->missing('proofs.0.file.disk')
                ->missing('proofs.0.file.tenant_id')
                ->where('proofs.0.file.category', 'transfer_proof'))
            ->viewData('page')['props']['proofs'][0];

        // Tautan berkas bertanda tangan dapat dibuka pemilik.
        $this->actingAs($this->ownerA)->get($queue['file']['url'])->assertOk();

        $proof = $this->inTenant($this->tenantA, fn () => PaymentProof::query()->sole());
        $this->actingAs($this->ownerA)->post("/app/bukti-transfer/{$proof->id}/setujui", ['method' => 'transfer'])->assertRedirect();

        $this->assertSame(ProofStatus::Approved, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->find($proof->id))->status);
        $this->assertSame(InvoiceStatus::Paid, $this->inTenant($this->tenantA, fn () => Invoice::query()->find($invoice->id))->status);

        $payment = $this->inTenant($this->tenantA, fn () => Payment::query()->sole());
        $this->actingAs($this->ownerA)->get("/app/pembayaran/{$payment->id}/kuitansi")->assertOk();

        // Tunggakan di daftar tagihan berubah: tidak ada lagi sisa lewat jatuh tempo.
        $this->actingAs($this->ownerA)->get('/app/tagihan?period=')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.overdue_amount', 0)
            ->where('summary.collected', 1500000));
    }

    public function test_penolakan_bukti_lewat_http_wajib_alasan(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $proof = $this->proofIn($this->tenantA, $invoice);

        $this->actingAs($this->ownerA)->post("/app/bukti-transfer/{$proof->id}/tolak", ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Alasan penolakan wajib diisi.']);

        $this->actingAs($this->ownerA)->post("/app/bukti-transfer/{$proof->id}/tolak", ['reason' => 'Buram'])->assertRedirect();

        $fresh = $this->inTenant($this->tenantA, fn () => PaymentProof::query()->find($proof->id));
        $this->assertSame(ProofStatus::Rejected, $fresh->status);
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Payment::query()->count()));
    }

    public function test_unggah_bukti_menolak_berkas_berbahaya_dan_terlalu_besar(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->create('x.php', 10, 'text/x-php')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->count()));
    }

    // Rekening

    public function test_hanya_pemilik_mengelola_rekening(): void
    {
        $body = ['type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '8801299311', 'account_name' => 'Pemilik Kos'];

        $this->actingAs($this->staffFinance)->post('/app/pengaturan/rekening', $body)->assertForbidden();
        $this->actingAs($this->ownerA)->post('/app/pengaturan/rekening', $body)->assertRedirect();

        $account = $this->inTenant($this->tenantA, fn () => PaymentAccount::query()->sole());
        $this->assertTrue($account->is_default);

        $this->actingAs($this->staffFinance)->patch("/app/pengaturan/rekening/{$account->id}", ['type' => 'bank', 'is_active' => false])->assertForbidden();
        $this->actingAs($this->ownerA)->get('/app/pengaturan/rekening')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Owner/Settings/PaymentAccounts')->has('accounts', 1)->where('can.manage', true));
    }

    public function test_validasi_rekening_bank_wajib_nomor_dan_nama(): void
    {
        $this->actingAs($this->ownerA)->post('/app/pengaturan/rekening', ['type' => 'bank'])
            ->assertSessionHasErrors(['bank_name', 'account_number', 'account_name']);
        $this->actingAs($this->ownerA)->post('/app/pengaturan/rekening', ['type' => 'qris'])->assertSessionHasNoErrors();
    }

    // Isolasi tenant lewat URL dan formulir

    public function test_tenant_lain_tidak_bisa_membuka_atau_mengubah_data_pembayaran(): void
    {
        $invoiceA = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $payment = $this->paymentIn($this->tenantA, $this->ownerA, $invoiceA, 600000);
        $proof = $this->proofIn($this->tenantA, $invoiceA);
        $account = $this->inTenant($this->tenantA, fn () => PaymentAccount::factory()->create());

        $this->actingAs($this->ownerB);

        $this->get("/app/pembayaran/{$payment->id}/kuitansi")->assertNotFound();
        $this->get("/app/pembayaran/{$payment->id}/kuitansi.pdf")->assertNotFound();
        $this->post("/app/pembayaran/{$payment->id}/batal", ['reason' => 'Serang'])->assertNotFound();
        $this->post("/app/bukti-transfer/{$proof->id}/setujui", ['method' => 'transfer'])->assertNotFound();
        $this->post("/app/bukti-transfer/{$proof->id}/tolak", ['reason' => 'Serang'])->assertNotFound();
        $this->post("/app/tagihan/{$invoiceA->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->image('b.jpg')])->assertNotFound();
        $this->patch("/app/pengaturan/rekening/{$account->id}", ['type' => 'bank', 'bank_name' => 'X', 'account_number' => '1', 'account_name' => 'Y'])->assertNotFound();

        // Alokasi ke tagihan tenant A lewat formulir tenant B ditolak validasi.
        $residentB = $this->inTenant($this->tenantB, fn () => Resident::factory()->create());
        $this->post('/app/pembayaran', [
            'resident_id' => $residentB->id, 'amount' => 900000, 'paid_on' => '2026-11-09', 'method' => 'cash',
            'allocations' => [['invoice_id' => $invoiceA->id, 'amount' => 900000]],
        ])->assertSessionHasErrors('allocations.0.invoice_id');

        // Rekening tenant A sebagai tujuan pembayaran tenant B ditolak.
        $invoiceB = $this->invoiceIn($this->tenantB, $this->leaseIn($this->tenantB, $this->propertyB));
        $this->post('/app/pembayaran', $this->paymentBody($invoiceB, ['method' => 'transfer', 'payment_account_id' => $account->id]))
            ->assertSessionHasErrors('payment_account_id');

        // Antrean, daftar, dan halaman tagihan tenant B tidak memuat data A.
        $this->get('/app/bukti-transfer')->assertInertia(fn (AssertableInertia $page) => $page->has('proofs', 0)->has('accounts', 0));
        $this->get('/app/pembayaran?period=')->assertInertia(fn (AssertableInertia $page) => $page->has('payments', 0));
        $this->get("/app/tagihan/{$invoiceA->id}")->assertNotFound();

        $this->assertSame(PaymentStatus::Active, $this->inTenant($this->tenantA, fn () => Payment::query()->find($payment->id))->status);
        $this->assertSame(ProofStatus::Pending, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->find($proof->id))->status);
    }

    public function test_berkas_bukti_tenant_lain_tidak_bisa_dibuka_lewat_url(): void
    {
        $invoiceA = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));

        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoiceA->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->image('b.jpg')]);
        $url = $this->actingAs($this->ownerA)->get('/app/bukti-transfer')->viewData('page')['props']['proofs'][0]['file']['url'];

        $this->actingAs($this->ownerB)->get($url)->assertNotFound();
    }

    public function test_staf_tanpa_izin_keuangan_tidak_bisa_menghapus_berkas_bukti(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->leaseIn($this->tenantA, $this->propertyA));
        $this->actingAs($this->ownerA)->post("/app/tagihan/{$invoice->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->image('b.jpg')]);
        $attachment = $this->inTenant($this->tenantA, fn () => Attachment::query()->sole());

        $this->actingAs($this->staffNoFinance)->delete("/app/lampiran/{$attachment->id}")->assertForbidden();
        $this->assertSame(1, $this->inTenant($this->tenantA, fn () => Attachment::query()->count()));
    }

    public function test_pengguna_tamu_diarahkan_ke_login(): void
    {
        $this->get('/app/pembayaran')->assertRedirect('/masuk');
        $this->post('/app/pembayaran', [])->assertRedirect('/masuk');
        $this->get('/app/bukti-transfer')->assertRedirect('/masuk');
    }
}
