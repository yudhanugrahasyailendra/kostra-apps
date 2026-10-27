<?php

namespace Tests\Feature\Payments;

use App\Actions\Billing\MarkOverdueInvoices;
use App\Actions\Billing\VoidInvoice;
use App\Actions\Payments\RecordPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PaymentRecorded;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseResident;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAllocation;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

class RecordPaymentTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    private function assertRejected(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail('Seharusnya ditolak pada kunci '.$key);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), implode(' | ', array_keys($e->errors())));
        }
    }

    private function paymentCount(): int
    {
        return $this->asTenant($this->tenant, fn () => Payment::count());
    }

    // ---- Alokasi dan status tagihan ----

    public function test_partial_payment_makes_the_invoice_partial_then_paid(): void
    {
        $invoice = $this->openInvoice();

        $first = $this->record([[$invoice, 400_000]]);
        $afterFirst = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Partial, 400_000, 1_100_000], [$afterFirst->status, $afterFirst->paid_total, $afterFirst->remaining()]);
        $this->assertNull($afterFirst->paid_at);

        $this->record([[$invoice, 1_100_000]]);
        $afterSecond = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Paid, 1_500_000, 0], [$afterSecond->status, $afterSecond->paid_total, $afterSecond->remaining()]);
        $this->assertNotNull($afterSecond->paid_at);
        $this->assertSame(400_000, $first->amount);
    }

    public function test_one_payment_settles_two_invoices_through_allocations(): void
    {
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        $this->generate('2026-09');
        $september = $this->openInvoice([], '2026-09', $lease);
        $october = $this->openInvoice([], '2026-10', $lease);

        $payment = $this->record([[$september, 1_500_000], [$october, 700_000]]);

        $this->assertSame(2_200_000, $payment->amount);
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($september)->status);
        $this->assertSame([InvoiceStatus::Partial, 700_000], [$this->fresh($october)->status, $this->fresh($october)->paid_total]);
        $this->assertSame(
            [1_500_000, 700_000],
            $this->asTenant($this->tenant, fn () => PaymentAllocation::where('payment_id', $payment->id)->orderBy('id')->pluck('amount')->all()),
        );
    }

    public function test_payment_amount_always_equals_the_sum_of_its_allocations(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 1_234_567]]);

        $this->assertSame(1_234_567, $payment->amount);
        $this->assertSame(1_234_567, (int) $this->asTenant($this->tenant, fn () => PaymentAllocation::where('payment_id', $payment->id)->sum('amount')));
    }

    public function test_an_overdue_invoice_becomes_paid_not_overdue_when_settled(): void
    {
        $invoice = $this->openInvoice();
        $this->travelTo('2026-11-20 03:00:00');
        $this->asTenant($this->tenant, fn () => app(MarkOverdueInvoices::class)());
        $this->assertSame(InvoiceStatus::Overdue, $this->fresh($invoice)->status);

        $this->record([[$invoice, 1_500_000]]);

        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);
    }

    public function test_a_partly_paid_invoice_past_due_stays_overdue(): void
    {
        $invoice = $this->openInvoice();
        $this->travelTo('2026-11-20 03:00:00');

        $this->record([[$invoice, 500_000]]);

        $fresh = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Overdue, 500_000], [$fresh->status, $fresh->paid_total]);
    }

    // ---- Penolakan ----

    public function test_overpayment_beyond_the_remaining_balance_is_rejected(): void
    {
        $invoice = $this->openInvoice();

        $this->assertRejected(fn () => $this->record([[$invoice, 1_500_001]]), "allocations.{$invoice->id}");

        $this->record([[$invoice, 1_000_000]]);
        $this->assertRejected(fn () => $this->record([[$invoice, 500_001]]), "allocations.{$invoice->id}");

        $this->assertSame(1, $this->paymentCount());
        $this->assertSame(1_000_000, $this->fresh($invoice)->paid_total);
    }

    public function test_the_error_message_names_the_remaining_balance(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 1_000_000]]);

        try {
            $this->record([[$invoice, 600_000]]);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Rp 500.000', $e->errors()["allocations.{$invoice->id}"][0]);
        }
    }

    public function test_paid_and_voided_invoices_cannot_receive_payments(): void
    {
        $paid = $this->openInvoice();
        $this->record([[$paid, 1_500_000]]);
        $this->assertRejected(fn () => $this->record([[$paid, 1]]), "allocations.{$paid->id}");

        $voided = $this->openInvoice();
        $this->asTenant($this->tenant, fn () => app(VoidInvoice::class)($voided, 'Salah terbit'));
        $this->assertRejected(fn () => $this->record([[$voided, 1_000]]), "allocations.{$voided->id}");

        $this->assertSame(1, $this->paymentCount());
    }

    public function test_each_allocation_must_be_a_positive_whole_amount_and_unique_per_invoice(): void
    {
        $invoice = $this->openInvoice();
        $call = fn (array $allocations) => fn () => $this->asTenant($this->tenant, fn () => app(RecordPayment::class)([
            'resident_id' => $invoice->resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash', 'allocations' => $allocations,
        ], $this->owner));

        $this->assertRejected($call([]), 'allocations');
        $this->assertRejected($call([['invoice_id' => $invoice->id, 'amount' => 0]]), 'allocations');
        $this->assertRejected($call([['invoice_id' => $invoice->id, 'amount' => -5]]), 'allocations');
        $this->assertRejected($call([['invoice_id' => $invoice->id, 'amount' => 1000.5]]), 'allocations');
        $this->assertRejected($call([['invoice_id' => $invoice->id, 'amount' => '1000']]), 'allocations');
        $this->assertRejected($call([['invoice_id' => $invoice->id, 'amount' => 100], ['invoice_id' => $invoice->id, 'amount' => 100]]), 'allocations');
        $this->assertRejected($call([['invoice_id' => 999_999, 'amount' => 100]]), 'allocations');

        $this->assertSame(0, $this->paymentCount());
    }

    public function test_only_members_of_the_lease_can_pay_its_invoices(): void
    {
        $invoice = $this->openInvoice();
        $stranger = Resident::factory()->for($this->tenant)->create();

        $this->assertRejected(fn () => $this->record([[$invoice, 100_000]], ['resident_id' => $stranger->id]), "allocations.{$invoice->id}");
        $this->assertSame(0, $this->paymentCount());
    }

    public function test_a_roommate_in_the_lease_may_pay(): void
    {
        $roommate = Resident::factory()->for($this->tenant)->create();
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => LeaseResident::create(['lease_id' => $lease->id, 'resident_id' => $roommate->id, 'is_primary' => false]));
        $invoice = $this->openInvoice([], null, $lease);

        $payment = $this->record([[$invoice, 100_000]], ['resident_id' => $roommate->id]);

        $this->assertSame($roommate->id, $payment->resident_id);
        $this->assertSame(InvoiceStatus::Partial, $this->fresh($invoice)->status);
    }

    public function test_another_tenants_invoice_cannot_be_paid(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $theirLease = $this->leaseFor([], $other);
        $this->generate(null, $other);
        $theirs = $this->asTenant($other, fn () => Invoice::where('lease_id', $theirLease->id)->firstOrFail());

        $this->assertRejected(fn () => $this->asTenant($this->tenant, fn () => app(RecordPayment::class)([
            'resident_id' => $this->leaseFor()->primary_resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash',
            'allocations' => [['invoice_id' => $theirs->id, 'amount' => 100_000]],
        ], $this->owner)), 'allocations');

        $this->assertSame(0, $this->asTenant($other, fn () => Payment::count()));
        $this->assertSame(0, $this->asTenant($other, fn () => Invoice::findOrFail($theirs->id)->paid_total));
    }

    public function test_date_method_and_account_rules(): void
    {
        $invoice = $this->openInvoice();
        $record = fn (array $o) => fn () => $this->record([[$invoice, 100_000]], $o);

        $this->assertRejected($record(['paid_on' => '2026-10-16']), 'paid_on');
        $this->assertRejected($record(['paid_on' => '2025-10-14']), 'paid_on');
        $this->assertRejected($record(['paid_on' => 'kemarin']), 'paid_on');
        $this->assertRejected($record(['method' => 'barter']), 'method');

        $account = $this->asTenant($this->tenant, fn () => PaymentAccount::create(['type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '8820192830', 'is_active' => true]));
        $inactive = $this->asTenant($this->tenant, fn () => PaymentAccount::create(['type' => 'bank', 'bank_name' => 'BNI', 'account_number' => '1234567', 'is_active' => false]));
        $other = Tenant::factory()->create();
        $foreign = $this->asTenant($other, fn () => PaymentAccount::create(['type' => 'bank', 'bank_name' => 'BRI', 'account_number' => '7654321', 'is_active' => true]));

        $this->assertRejected($record(['method' => 'cash', 'payment_account_id' => $account->id]), 'payment_account_id');
        $this->assertRejected($record(['method' => 'transfer', 'payment_account_id' => $inactive->id]), 'payment_account_id');
        $this->assertRejected($record(['method' => 'transfer', 'payment_account_id' => $foreign->id]), 'payment_account_id');
        $this->assertSame(0, $this->paymentCount());

        $ok = $this->record([[$invoice, 100_000]], ['method' => 'transfer', 'payment_account_id' => $account->id, 'reference' => 'TRX-1']);
        $this->assertSame([PaymentMethod::Transfer, $account->id, 'TRX-1'], [$ok->method, $ok->payment_account_id, $ok->reference]);

        // Batas tepat satu tahun ke belakang masih diterima.
        $this->record([[$invoice, 100_000]], ['paid_on' => '2025-10-15']);
        $this->assertSame(2, $this->paymentCount());
    }

    // ---- Kuitansi, atomisitas, jejak ----

    public function test_each_payment_gets_a_unique_gapless_receipt_number_per_tenant(): void
    {
        $a = $this->openInvoice();
        $b = $this->openInvoice();
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        User::factory()->for($other)->create();
        $theirLease = $this->leaseFor([], $other);
        $this->generate(null, $other);
        $theirs = $this->asTenant($other, fn () => Invoice::where('lease_id', $theirLease->id)->firstOrFail());

        $one = $this->record([[$a, 100_000]]);
        $two = $this->record([[$b, 100_000]]);
        $three = $this->record([[$a, 100_000]]);
        $foreign = $this->record([[$theirs, 100_000]], [], User::where('tenant_id', $other->id)->first() ?? $this->owner, $other);

        $this->assertSame(['KWT-2026-10-0001', 'KWT-2026-10-0002', 'KWT-2026-10-0003'], [$one->receipt_number, $two->receipt_number, $three->receipt_number]);
        $this->assertSame('KWT-2026-10-0001', $foreign->receipt_number);
        $this->assertNotNull($one->receipt_issued_at);
        $this->assertNull($one->receipt_sent_at);
    }

    public function test_the_database_forbids_a_duplicate_receipt_number_within_a_tenant(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 100_000]]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->asTenant($this->tenant, fn () => Payment::create([
            'resident_id' => $payment->resident_id, 'amount' => 1000, 'paid_on' => '2026-10-15', 'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Active, 'recorded_by' => $this->owner->id, 'receipt_number' => $payment->receipt_number,
        ]));
    }

    public function test_a_failure_in_the_middle_leaves_no_payment_no_allocation_and_no_burned_receipt_number(): void
    {
        $invoice = $this->openInvoice();
        $explode = true;
        PaymentAllocation::creating(function () use (&$explode) {
            if ($explode) {
                throw new RuntimeException('alokasi gagal');
            }
        });

        try {
            $this->record([[$invoice, 500_000]]);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertSame(0, $this->paymentCount());
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentAllocation::count()));
        $fresh = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Issued, 0], [$fresh->status, $fresh->paid_total]);

        $explode = false;
        $payment = $this->record([[$invoice, 500_000]]);

        $this->assertSame('KWT-2026-10-0001', $payment->receipt_number, 'Nomor kuitansi yang gagal harus dikembalikan, tanpa lubang.');
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => NumberSequence::where('key', 'receipt')->firstOrFail()->last_number));
    }

    public function test_the_recorded_event_fires_once_and_only_on_success(): void
    {
        Event::fake([PaymentRecorded::class]);
        $invoice = $this->openInvoice();

        $this->assertRejected(fn () => $this->record([[$invoice, 9_999_999]]), "allocations.{$invoice->id}");
        Event::assertNotDispatched(PaymentRecorded::class);

        $payment = $this->record([[$invoice, 100_000]]);
        Event::assertDispatchedTimes(PaymentRecorded::class, 1);
        Event::assertDispatched(PaymentRecorded::class, fn (PaymentRecorded $e) => $e->paymentId === $payment->id && $e->tenantId === $this->tenant->id);
    }

    public function test_the_payment_is_audited_with_the_person_who_recorded_it(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 100_000]]);

        $log = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'payment')->where('auditable_id', $payment->id)->where('event', 'created')->sole());
        $this->assertEquals(100_000, $log->new_values['amount']);
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame($this->owner->id, $payment->recorded_by);
    }

    public function test_money_columns_are_integers_never_floats(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 1_234_567]]);

        $this->assertIsInt($payment->amount);
        foreach (Schema::getColumns('payments') as $column) {
            if ($column['name'] === 'amount') {
                $this->assertStringContainsString('bigint', $column['type_name']);
            }
        }
        foreach (Schema::getColumns('payment_allocations') as $column) {
            if ($column['name'] === 'amount') {
                $this->assertStringContainsString('bigint', $column['type_name']);
            }
        }
    }

    public function test_a_payment_must_be_recorded_inside_a_transaction(): void
    {
        // Pencatatan lewat persist() di luar transaksi akan membocorkan kunci dan nomor; harus ditolak.
        $this->expectException(\LogicException::class);

        $this->asTenant($this->tenant, function () {
            \DB::rollBack(); // keluar dari transaksi pembungkus tes
            try {
                app(RecordPayment::class)->persist([], $this->owner);
            } finally {
                \DB::beginTransaction();
            }
        });
    }

    // ---- HTTP ----

    public function test_owner_and_staff_record_payments_through_the_form(): void
    {
        $invoice = $this->openInvoice();
        $staff = User::factory()->for($this->tenant)->staff()->create();

        foreach ([$this->owner, $staff] as $user) {
            $this->actingAs($user)->post(route('owner.payments.store'), [
                'resident_id' => $invoice->resident_id,
                'paid_on' => '2026-10-15',
                'method' => 'cash',
                'allocations' => [$invoice->id => 'Rp 300.000', 9999 => ''],
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(600_000, $this->fresh($invoice)->paid_total);
        $this->assertSame(2, $this->paymentCount());
    }

    public function test_the_form_reports_which_invoice_is_overpaid(): void
    {
        $invoice = $this->openInvoice();

        $this->actingAs($this->owner)->post(route('owner.payments.store'), [
            'resident_id' => $invoice->resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash',
            'allocations' => [$invoice->id => '1.500.001'],
        ])->assertSessionHasErrors("allocations.{$invoice->id}");

        $this->actingAs($this->owner)->post(route('owner.payments.store'), [
            'resident_id' => $invoice->resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash',
            'allocations' => [$invoice->id => '1500.50'],
        ])->assertSessionHasErrors('allocations.0.amount');

        $this->actingAs($this->owner)->post(route('owner.payments.store'), [
            'resident_id' => $invoice->resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash', 'allocations' => [],
        ])->assertSessionHasErrors('allocations');

        $this->assertSame(0, $this->paymentCount());
    }

    public function test_suspended_tenants_cannot_record_payments(): void
    {
        $invoice = $this->openInvoice();
        $this->asTenant($this->tenant, fn () => $this->tenant->update(['status' => 'suspended']));

        $this->actingAs($this->owner)->post(route('owner.payments.store'), [
            'resident_id' => $invoice->resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash',
            'allocations' => [$invoice->id => '100.000'],
        ])->assertForbidden();

        $this->assertSame(0, $this->paymentCount());
    }

    public function test_the_checkout_page_shows_the_balance_left_after_payments(): void
    {
        $invoice = $this->openInvoice();
        $lease = $this->asTenant($this->tenant, fn () => Lease::findOrFail($invoice->lease_id));
        $this->record([[$invoice, 600_000]]);

        $this->actingAs($this->owner)->get(route('owner.leases.show', $lease))
            ->assertOk()->assertSee('Sisa tagihan belum dibayar')->assertSee('Rp 900.000');
    }
}
