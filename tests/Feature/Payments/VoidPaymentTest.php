<?php

namespace Tests\Feature\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

class VoidPaymentTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    private function void(Payment $payment, string $reason = 'Salah catat nominal', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.payments.void', $payment), ['reason' => $reason]);
    }

    private function fresher(Payment $payment): Payment
    {
        return $this->asTenant($this->tenant, fn () => Payment::findOrFail($payment->id));
    }

    public function test_voiding_a_full_payment_returns_the_invoice_to_unpaid(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 1_500_000]]);
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);

        $this->void($payment)->assertSessionHasNoErrors()->assertRedirect(route('owner.payments.show', $payment));

        $invoiceAfter = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Issued, 0, 1_500_000], [$invoiceAfter->status, $invoiceAfter->paid_total, $invoiceAfter->remaining()]);
        $this->assertNull($invoiceAfter->paid_at);
    }

    public function test_voiding_one_of_two_payments_leaves_the_invoice_partial(): void
    {
        $invoice = $this->openInvoice();
        $first = $this->record([[$invoice, 400_000]]);
        $this->record([[$invoice, 600_000]]);

        $this->void($first)->assertSessionHasNoErrors();

        $fresh = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Partial, 600_000], [$fresh->status, $fresh->paid_total]);
    }

    public function test_voiding_after_the_due_date_makes_the_invoice_overdue_again(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 1_500_000]]);

        $this->travelTo('2026-11-20 03:00:00');
        $this->void($payment)->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatus::Overdue, $this->fresh($invoice)->status);
    }

    public function test_voiding_a_multi_invoice_payment_restores_every_invoice(): void
    {
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        $this->generate('2026-09');
        $september = $this->openInvoice([], '2026-09', $lease);
        $october = $this->openInvoice([], '2026-10', $lease);
        $payment = $this->record([[$september, 1_500_000], [$october, 1_500_000]]);

        $this->void($payment)->assertSessionHasNoErrors();

        $this->assertSame([0, 0], [$this->fresh($september)->paid_total, $this->fresh($october)->paid_total]);
        $this->assertNotSame(InvoiceStatus::Paid, $this->fresh($september)->status);
        $this->assertNotSame(InvoiceStatus::Paid, $this->fresh($october)->status);
    }

    public function test_payment_row_allocations_and_receipt_number_are_kept(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 700_000]]);

        $this->void($payment, 'Uang belum masuk');

        $voided = $this->fresher($payment);
        $this->assertSame(PaymentStatus::Voided, $voided->status);
        $this->assertSame('Uang belum masuk', $voided->void_reason);
        $this->assertSame($this->owner->id, $voided->voided_by);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame($payment->receipt_number, $voided->receipt_number);
        $this->assertSame(700_000, $voided->amount);
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => PaymentAllocation::where('payment_id', $payment->id)->count()));
    }

    public function test_a_voided_amount_can_be_paid_again_without_being_double_counted(): void
    {
        $invoice = $this->openInvoice();
        $first = $this->record([[$invoice, 1_500_000]]);
        $this->void($first);

        $this->record([[$invoice, 1_500_000]]);

        $fresh = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Paid, 1_500_000], [$fresh->status, $fresh->paid_total]);
    }

    public function test_reason_is_required_and_a_payment_cannot_be_voided_twice(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);

        $this->void($payment, '')->assertSessionHasErrors('reason');
        $this->void($payment, 'ab')->assertSessionHasErrors('reason');
        $this->assertSame(PaymentStatus::Active, $this->fresher($payment)->status);

        $this->void($payment)->assertSessionHasNoErrors();
        $this->void($payment, 'Sekali lagi')->assertSessionHasErrors('general');
        $this->assertSame(0, $this->fresh($invoice)->paid_total);
    }

    public function test_only_the_owner_or_staff_with_the_void_permission_may_void(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);
        $plain = User::factory()->for($this->tenant)->staff()->create();
        $allowed = User::factory()->for($this->tenant)->staff(['payments.void'])->create();

        $this->void($payment, 'Coba batal', $plain)->assertForbidden();
        $this->assertSame(PaymentStatus::Active, $this->fresher($payment)->status);
        $this->assertSame(500_000, $this->fresh($invoice)->paid_total);

        $this->actingAs($plain)->get(route('owner.payments.show', $payment))->assertOk()->assertDontSee('Batalkan pembayaran');
        $this->actingAs($allowed)->get(route('owner.payments.show', $payment))->assertOk()->assertSee('Batalkan pembayaran');

        $this->void($payment, 'Staf berizin', $allowed)->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Voided, $this->fresher($payment)->status);
    }

    public function test_the_permission_is_offered_on_the_staff_page(): void
    {
        $this->actingAs($this->owner)->get(route('owner.staff.index'))->assertOk()->assertSee('Membatalkan pembayaran');
    }

    public function test_voiding_is_audited_and_never_deletes(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);

        $this->void($payment, 'Salah catat');

        $log = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'payment')->where('auditable_id', $payment->id)->where('event', 'updated')->latest('id')->firstOrFail());
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame('active', $log->old_values['status']);
        $this->assertSame('voided', $log->new_values['status']);
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Payment::count()));

        $this->actingAs($this->owner)->delete('/owner/payments/'.$payment->id)->assertStatus(405);
        $names = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter();
        $this->assertFalse($names->contains(fn ($n) => str_contains($n, 'payments.destroy')));
    }

    public function test_suspended_tenants_cannot_void(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);
        $this->asTenant($this->tenant, fn () => $this->tenant->update(['status' => 'suspended']));

        $this->void($payment)->assertForbidden();
        $this->assertSame(PaymentStatus::Active, $this->fresher($payment)->status);
    }

    public function test_adjustments_cannot_push_an_invoice_below_what_was_actually_paid(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 1_000_000]]);

        $this->actingAs($this->owner)->post(route('owner.invoices.adjust', $invoice), [
            'direction' => 'decrease', 'amount' => 600_000, 'reason' => 'Diskon terlalu besar',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->owner)->post(route('owner.invoices.adjust', $invoice), [
            'direction' => 'decrease', 'amount' => 500_000, 'reason' => 'Diskon pas',
        ])->assertSessionHasNoErrors();

        // Total turun menjadi sama dengan yang terbayar: tagihan menjadi lunas.
        $this->assertSame([InvoiceStatus::Paid, 1_000_000], [$this->fresh($invoice)->status, $this->fresh($invoice)->total]);
    }

    public function test_an_invoice_with_a_payment_cannot_be_voided_until_the_payment_is(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);

        $this->actingAs($this->owner)->post(route('owner.invoices.void', $invoice), ['reason' => 'Batalkan tagihan'])->assertSessionHasErrors('general');

        $this->void($payment);
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $invoice), ['reason' => 'Batalkan tagihan'])->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Void, $this->fresh($invoice)->status);
    }
}
