<?php

namespace Tests\Feature\Payments;

use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    public function test_receipt_shows_the_number_payer_amount_and_the_invoices_it_settles(): void
    {
        $invoice = $this->openInvoice();
        $resident = $this->asTenant($this->tenant, fn () => Resident::findOrFail($invoice->resident_id));
        $payment = $this->record([[$invoice, 1_250_000]], ['method' => 'transfer', 'reference' => 'TRX-889']);

        $this->actingAs($this->owner)->get(route('owner.payments.receipt', $payment))
            ->assertOk()
            ->assertSee('KWT-2026-10-0001')
            ->assertSee($this->tenant->name)
            ->assertSee($resident->name)
            ->assertSee('Rp 1.250.000')
            ->assertSee($invoice->number)
            ->assertSee('Transfer bank')
            ->assertSee('TRX-889')
            ->assertSee('Cetak atau simpan PDF')
            ->assertDontSee('DIBATALKAN');
    }

    public function test_a_voided_payments_receipt_is_marked_cancelled_but_keeps_its_number(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);
        $this->actingAs($this->owner)->post(route('owner.payments.void', $payment), ['reason' => 'Uang belum masuk']);

        $this->actingAs($this->owner)->get(route('owner.payments.receipt', $payment))
            ->assertOk()
            ->assertSee('KWT-2026-10-0001')
            ->assertSee('DIBATALKAN')
            ->assertSee('Uang belum masuk');
    }

    public function test_staff_can_open_receipts_and_other_tenants_cannot(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)->get(route('owner.payments.receipt', $payment))->assertOk();

        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->for($other)->create();
        $this->actingAs($stranger)->get(route('owner.payments.receipt', $payment))->assertNotFound();
        $this->actingAs($stranger)->get(route('owner.payments.show', $payment))->assertNotFound();
    }

    public function test_receipts_require_login_and_are_not_indexed(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);

        $this->get(route('owner.payments.receipt', $payment))->assertRedirect(route('login'));
        $this->actingAs($this->owner)->get(route('owner.payments.receipt', $payment))->assertSee('noindex', false);
    }

    public function test_receipt_can_be_downloaded_as_pdf_named_after_its_number(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);

        $response = $this->actingAs($this->owner)->get(route('owner.payments.receipt.pdf', $payment));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('KWT-2026-10-0001.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_voided_payment_receipt_pdf_is_still_available(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);
        $this->actingAs($this->owner)->post(route('owner.payments.void', $payment), ['reason' => 'Uang belum masuk']);

        $this->actingAs($this->owner)->get(route('owner.payments.receipt.pdf', $payment))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_view_renders_without_the_screen_stylesheet(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 1_250_000]], ['reference' => 'TRX-889']);
        $html = $this->asTenant($this->tenant, function () use ($payment) {
            $payment->load(['resident:id,name', 'account', 'allocations.invoice.items', 'recorder:id,name']);

            return view('owner.payments.receipt-pdf', ['payment' => $payment, 'tenant' => $this->tenant])->render();
        });

        $this->assertStringContainsString('KWT-2026-10-0001', $html);
        $this->assertStringContainsString('Rp 1.250.000', $html);
        $this->assertStringContainsString('TRX-889', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringNotContainsString('http', str_replace('http://www.w3.org', '', $html));
    }

    public function test_pdf_requires_login_and_respects_tenant_isolation(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);

        $this->get(route('owner.payments.receipt.pdf', $payment))->assertRedirect(route('login'));

        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->for($other)->create();
        $this->actingAs($stranger)->get(route('owner.payments.receipt.pdf', $payment))->assertNotFound();
    }

    public function test_a_receipt_covers_several_invoices(): void
    {
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        $this->generate('2026-09');
        $september = $this->openInvoice([], '2026-09', $lease);
        $october = $this->openInvoice([], '2026-10', $lease);
        $payment = $this->record([[$september, 1_500_000], [$october, 300_000]]);

        $this->actingAs($this->owner)->get(route('owner.payments.receipt', $payment))
            ->assertOk()->assertSee($september->number)->assertSee($october->number)->assertSee('Rp 1.800.000');
    }
}
