<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAccount;
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

/** Panel catat pembayaran di daftar tagihan: baris bisa dibuka, panel tagihan, dan layar berhasil. */
class InvoicePaymentPanelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    private User $staffNoFinance;

    private Property $propertyA;

    private Property $propertyB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->staffNoFinance = User::factory()->forTenant($this->tenantA)->staff()->create();
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

    private function invoiceIn(Tenant $tenant, Property $property, array $attributes = []): Invoice
    {
        return $this->inTenant($tenant, function () use ($property, $attributes) {
            $room = Room::factory()->create(['property_id' => $property->id]);
            $resident = Resident::factory()->create();
            $lease = Lease::factory()->create(['property_id' => $property->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id]);

            return Invoice::factory()->forLease($lease)->create($attributes);
        });
    }

    /** @return array<string, mixed> */
    private function paymentBody(Invoice $invoice, int $amount, array $overrides = []): array
    {
        return $overrides + [
            'resident_id' => $invoice->resident_id,
            'amount' => $amount,
            'paid_on' => '2026-11-09',
            'method' => 'cash',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
            'stay' => true,
        ];
    }

    public function test_baris_daftar_memuat_item_dan_izin_catat_bayar(): void
    {
        $open = $this->invoiceIn($this->tenantA, $this->propertyA);
        $this->invoiceIn($this->tenantA, $this->propertyA, ['status' => InvoiceStatus::Paid, 'paid_total' => 1500000]);

        $this->actingAs($this->ownerA)->get('/app/tagihan?status=all')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Invoices/Index')
                ->has('invoices', 2)
                ->where('invoices', fn ($invoices) => collect($invoices)->firstWhere('id', $open->id)['can']['pay'] === true
                    && collect($invoices)->firstWhere('id', '!=', $open->id)['can']['pay'] === false
                    && count(collect($invoices)->firstWhere('id', $open->id)['items']) === 1)
                ->where('panel', null)
                ->where('recorded_payment', null)
                ->has('payment_options.methods', 5)
                ->missing('payment_options.accounts.0.tenant_id'));
    }

    public function test_panel_memuat_detail_tagihan_dan_rekening_aktif(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->propertyA);
        $this->inTenant($this->tenantA, function () {
            PaymentAccount::factory()->create(['is_default' => true, 'bank_name' => 'BCA']);
            PaymentAccount::factory()->create(['is_active' => false]);
        });

        $this->actingAs($this->ownerA)->get('/app/tagihan?panel='.$invoice->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('panel.invoice.id', $invoice->id)
                ->where('panel.invoice.outstanding', 1500000)
                ->has('panel.invoice.items', 1)
                ->has('panel.invoice.adjustments', 0)
                ->has('panel.payments', 0)
                ->missing('panel.invoice.tenant_id')
                ->has('payment_options.accounts', 1)
                ->where('payment_options.accounts.0.is_default', true)
                ->where('payment_options.today', '2026-11-09'));
    }

    public function test_panel_tagihan_tenant_lain_kosong(): void
    {
        $foreign = $this->invoiceIn($this->tenantB, $this->propertyB);

        $this->actingAs($this->ownerA)->get('/app/tagihan?panel='.$foreign->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('panel', null));
    }

    public function test_staf_tanpa_izin_keuangan_tidak_bisa_membuka_daftar(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->propertyA);

        $this->actingAs($this->staffNoFinance)->get('/app/tagihan?panel='.$invoice->id)->assertForbidden();
    }

    public function test_bayar_dari_panel_kembali_ke_daftar_dengan_data_berhasil(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->propertyA);

        $this->actingAs($this->ownerA)
            ->from('/app/tagihan?panel='.$invoice->id)
            ->post('/app/pembayaran', $this->paymentBody($invoice, 500000, ['notes' => 'Cicilan pertama']))
            ->assertRedirect('/app/tagihan?panel='.$invoice->id)
            ->assertSessionHasNoErrors();

        $payment = $this->inTenant($this->tenantA, fn () => Payment::query()->sole());

        $this->actingAs($this->ownerA)->get('/app/tagihan?panel='.$invoice->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recorded_payment.id', $payment->id)
                ->where('recorded_payment.amount', 500000)
                ->where('recorded_payment.method_label', 'Tunai')
                ->where('recorded_payment.lines.0.invoice_number', $invoice->number)
                ->where('panel.invoice.status', 'partial')
                ->where('panel.invoice.outstanding', 1000000)
                ->has('panel.payments', 1));

        // Layar berhasil hanya sekali: muat berikutnya tanpa data pembayaran baru.
        $this->actingAs($this->ownerA)->get('/app/tagihan?panel='.$invoice->id)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recorded_payment', null));
    }

    public function test_tanpa_stay_tetap_diarahkan_ke_kuitansi(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->propertyA);

        $response = $this->actingAs($this->ownerA)->post('/app/pembayaran', $this->paymentBody($invoice, 1500000, ['stay' => false]));

        $payment = $this->inTenant($this->tenantA, fn () => Payment::query()->sole());
        $response->assertRedirect(route('owner.payments.receipt', $payment));
    }

    public function test_kelebihan_bayar_dari_panel_ditolak(): void
    {
        $invoice = $this->invoiceIn($this->tenantA, $this->propertyA);

        $this->actingAs($this->ownerA)
            ->from('/app/tagihan?panel='.$invoice->id)
            ->post('/app/pembayaran', $this->paymentBody($invoice, 2000000))
            ->assertSessionHasErrors();

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Payment::query()->count()));
    }

    public function test_pembayaran_tenant_lain_di_sesi_tidak_bocor(): void
    {
        $foreignInvoice = $this->invoiceIn($this->tenantB, $this->propertyB);
        $this->actingAs($this->ownerB)->post('/app/pembayaran', $this->paymentBody($foreignInvoice, 1500000));
        $foreignPayment = $this->inTenant($this->tenantB, fn () => Payment::query()->sole());

        $this->actingAs($this->ownerA)
            ->withSession(['recorded_payment_id' => $foreignPayment->id])
            ->get('/app/tagihan')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recorded_payment', null));
    }
}
