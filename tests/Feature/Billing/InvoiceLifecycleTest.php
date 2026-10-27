<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\MarkOverdueInvoices;
use App\Actions\Billing\RecalculateInvoice;
use App\Contracts\InvoicePaidTotal;
use App\Contracts\OutstandingBalance;
use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BillingFixtures;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use BillingFixtures, RefreshDatabase;

    private Lease $lease;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
        $this->lease = $this->leaseFor();
        $this->generate();
        $this->invoice = $this->invoices()->sole();
    }

    private function fresh(): Invoice
    {
        return $this->asTenant($this->tenant, fn () => Invoice::findOrFail($this->invoice->id));
    }

    /**
     * Mensimulasikan pembayaran Fase 6: terbayar ditentukan peta ini.
     *
     * @param  array<int, int>  $paid  invoice id => jumlah terbayar
     */
    private function bindPaid(array $paid): void
    {
        $this->app->bind(InvoicePaidTotal::class, fn () => new class($paid) implements InvoicePaidTotal
        {
            public function __construct(private array $paid) {}

            public function forInvoice(Invoice $invoice): int
            {
                return $this->paid[$invoice->getKey()] ?? 0;
            }
        });
    }

    private function recalc(): Invoice
    {
        return $this->asTenant($this->tenant, fn () => app(RecalculateInvoice::class)($this->invoice));
    }

    private function adjust(int $amount, string $direction, string $reason = 'Diskon keluhan AC', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.invoices.adjust', $this->invoice), [
            'direction' => $direction, 'amount' => $amount, 'reason' => $reason,
        ]);
    }

    // ---- Penyesuaian ----

    public function test_adjustment_changes_the_total_but_never_the_original_items(): void
    {
        $this->adjust(100_000, 'decrease')->assertSessionHasNoErrors();

        $invoice = $this->fresh();
        $this->assertSame(1_500_000, $invoice->subtotal);
        $this->assertSame(-100_000, $invoice->adjustment_total);
        $this->assertSame(1_400_000, $invoice->total);
        $this->assertSame(1_500_000, $this->invoices()->sole()->items->sole()->amount);

        $adjustment = $this->asTenant($this->tenant, fn () => InvoiceAdjustment::sole());
        $this->assertSame(-100_000, $adjustment->amount);
        $this->assertSame('Diskon keluhan AC', $adjustment->reason);
        $this->assertSame($this->owner->id, $adjustment->created_by);
    }

    public function test_total_always_equals_subtotal_plus_adjustments(): void
    {
        $this->adjust(100_000, 'decrease');
        $this->adjust(250_000, 'increase', 'Denda kunci hilang');
        $this->adjust(50_000, 'decrease', 'Pembulatan');

        $invoice = $this->fresh();
        $this->assertSame(100_000, $invoice->adjustment_total);
        $this->assertSame($invoice->subtotal + $invoice->adjustment_total, $invoice->total);
        $this->assertSame(1_600_000, $invoice->total);
        $this->assertSame(3, $this->asTenant($this->tenant, fn () => InvoiceAdjustment::count()));
    }

    public function test_adjustment_rules(): void
    {
        $this->adjust(50_000, 'decrease', '')->assertSessionHasErrors('reason');
        $this->adjust(50_000, 'decrease', 'ab')->assertSessionHasErrors('reason');
        $this->adjust(0, 'decrease')->assertSessionHasErrors('amount');
        $this->adjust(50_000, 'sideways')->assertSessionHasErrors('direction');

        // Tidak boleh menjadi Rp 0 atau negatif: batalkan tagihan bila memang tidak ditagih.
        $this->adjust(1_500_000, 'decrease')->assertSessionHasErrors('amount');
        $this->adjust(2_000_000, 'decrease')->assertSessionHasErrors('amount');
        $this->adjust(1_499_999, 'decrease')->assertSessionHasNoErrors();

        $this->assertSame(1, $this->fresh()->total);
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => InvoiceAdjustment::count()));
    }

    public function test_adjustment_cannot_go_below_what_is_already_paid(): void
    {
        $this->bindPaid([$this->invoice->id => 1_000_000]);

        $this->adjust(600_000, 'decrease')->assertSessionHasErrors('amount');
        $this->adjust(500_000, 'decrease')->assertSessionHasNoErrors();

        $this->assertSame(1_000_000, $this->fresh()->total);
    }

    public function test_voided_invoices_cannot_be_adjusted(): void
    {
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Salah terbit'])->assertSessionHasNoErrors();

        $this->adjust(100_000, 'decrease')->assertSessionHasErrors('general');
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => InvoiceAdjustment::count()));
    }

    public function test_adjustments_are_audited(): void
    {
        $this->adjust(100_000, 'decrease');

        $log = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'invoice_adjustment')->where('event', 'created')->sole());
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertEquals(-100_000, $log->new_values['amount']);

        $update = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'invoice')->where('auditable_id', $this->invoice->id)->where('event', 'updated')->latest('id')->first());
        $this->assertEquals(1_500_000, $update->old_values['total']);
        $this->assertEquals(1_400_000, $update->new_values['total']);
    }

    // ---- Pembatalan ----

    public function test_void_keeps_the_row_and_number_and_requires_a_reason(): void
    {
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(InvoiceStatus::Issued, $this->fresh()->status);

        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Penghuni batal masuk'])
            ->assertRedirect(route('owner.invoices.show', $this->invoice));

        $invoice = $this->fresh();
        $this->assertSame(InvoiceStatus::Void, $invoice->status);
        $this->assertSame('Penghuni batal masuk', $invoice->void_reason);
        $this->assertNotNull($invoice->voided_at);
        $this->assertSame('INV-2026-10-0001', $invoice->number);
        $this->assertSame(1_500_000, $invoice->total);

        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Sekali lagi'])->assertSessionHasErrors('general');
    }

    public function test_paid_invoices_and_invoices_with_payments_cannot_be_voided(): void
    {
        $this->bindPaid([$this->invoice->id => 500_000]);
        $this->recalc();
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Coba batal'])->assertSessionHasErrors('general');
        $this->assertSame(InvoiceStatus::Partial, $this->fresh()->status);

        $this->bindPaid([$this->invoice->id => 1_500_000]);
        $this->recalc();
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Coba batal'])->assertSessionHasErrors('general');
        $this->assertSame(InvoiceStatus::Paid, $this->fresh()->status);
    }

    public function test_there_is_no_way_to_delete_an_invoice(): void
    {
        $this->actingAs($this->owner)->delete('/owner/invoices/'.$this->invoice->id)->assertStatus(405);
        $this->assertSame(1, $this->invoices()->count());

        $names = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter();
        $this->assertFalse($names->contains(fn ($name) => str_contains($name, 'invoices.destroy')));
    }

    public function test_staff_cannot_adjust_or_void(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->adjust(100_000, 'decrease', 'Diskon', $staff)->assertForbidden();
        $this->actingAs($staff)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Batal'])->assertForbidden();

        $this->assertSame(InvoiceStatus::Issued, $this->fresh()->status);
        $this->assertSame(1_500_000, $this->fresh()->total);
    }

    public function test_suspended_tenants_cannot_change_invoices(): void
    {
        $this->asTenant($this->tenant, fn () => $this->tenant->update(['status' => 'suspended']));

        $this->actingAs($this->owner)->get(route('owner.invoices.show', $this->invoice))->assertOk();
        $this->adjust(100_000, 'decrease')->assertForbidden();
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Batal'])->assertForbidden();
    }

    // ---- Status ----

    public function test_status_follows_paid_total_and_due_date(): void
    {
        $this->assertSame(InvoiceStatus::Issued, $this->fresh()->status);

        $this->bindPaid([$this->invoice->id => 400_000]);
        $partial = $this->recalc();
        $this->assertSame([InvoiceStatus::Partial, 400_000], [$partial->status, $partial->paid_total]);

        $this->bindPaid([$this->invoice->id => 1_500_000]);
        $paid = $this->recalc();
        $this->assertSame(InvoiceStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);

        // Pembayaran dibatalkan setelah lewat jatuh tempo: kembali menjadi terlambat (bukan "terbit").
        $this->travelTo('2026-11-20 03:00:00');
        $this->bindPaid([]);
        $reverted = $this->recalc();
        $this->assertSame([InvoiceStatus::Overdue, 0], [$reverted->status, $reverted->paid_total]);
        $this->assertNull($reverted->paid_at);

        // Terbayar sebagian setelah jatuh tempo tetap terlambat; lunas mengalahkan terlambat.
        $this->bindPaid([$this->invoice->id => 400_000]);
        $this->assertSame(InvoiceStatus::Overdue, $this->recalc()->status);
        $this->bindPaid([$this->invoice->id => 1_500_000]);
        $this->assertSame(InvoiceStatus::Paid, $this->recalc()->status);
    }

    public function test_overpayment_is_never_accepted(): void
    {
        $this->bindPaid([$this->invoice->id => 1_500_001]);

        $this->expectException(LogicException::class);

        $this->recalc();
    }

    public function test_voided_invoices_are_not_recalculated(): void
    {
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $this->invoice), ['reason' => 'Salah terbit']);
        $this->bindPaid([$this->invoice->id => 100]);

        $this->assertSame(InvoiceStatus::Void, $this->recalc()->status);
    }

    public function test_overdue_marking_by_the_scheduler(): void
    {
        $mark = fn () => $this->asTenant($this->tenant, fn () => app(MarkOverdueInvoices::class)());

        // Jatuh tempo = hari terbit (15 Okt): tepat hari jatuh tempo belum terlambat.
        $this->assertSame(0, $mark());
        $this->assertSame(InvoiceStatus::Issued, $this->fresh()->status);

        $this->travelTo('2026-10-16 03:00:00');
        $this->assertSame(1, $mark());
        $this->assertSame(InvoiceStatus::Overdue, $this->fresh()->status);

        // Dijalankan ulang: tidak ada perubahan lagi.
        $this->assertSame(0, $mark());
    }

    public function test_overdue_marking_leaves_paid_and_void_invoices_alone(): void
    {
        $paidLease = $this->leaseFor();
        $voidLease = $this->leaseFor();
        $this->generate();

        $byLease = $this->invoices()->keyBy('lease_id');
        $this->asTenant($this->tenant, function () use ($byLease, $paidLease, $voidLease) {
            $byLease[$paidLease->id]->update(['status' => InvoiceStatus::Paid, 'paid_total' => 1_500_000, 'paid_at' => now()]);
            $byLease[$voidLease->id]->update(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => 'x']);
        });

        $this->travelTo('2026-12-01 03:00:00');
        $changed = $this->asTenant($this->tenant, fn () => app(MarkOverdueInvoices::class)());

        $this->assertSame(1, $changed);
        $after = $this->invoices()->keyBy('lease_id');
        $this->assertSame(InvoiceStatus::Overdue, $after[$this->lease->id]->status);
        $this->assertSame(InvoiceStatus::Paid, $after[$paidLease->id]->status);
        $this->assertSame(InvoiceStatus::Void, $after[$voidLease->id]->status);
    }

    public function test_partial_invoices_also_become_overdue(): void
    {
        $this->asTenant($this->tenant, fn () => $this->invoice->update(['status' => InvoiceStatus::Partial, 'paid_total' => 400_000]));

        $this->travelTo('2026-10-20 03:00:00');
        $this->asTenant($this->tenant, fn () => app(MarkOverdueInvoices::class)());

        $this->assertSame(InvoiceStatus::Overdue, $this->fresh()->status);
    }

    // ---- Sisa tagihan untuk check-out ----

    public function test_outstanding_balance_is_computed_from_open_invoices(): void
    {
        $this->actingAs($this->owner)->post(route('owner.invoices.store'), [
            'lease_id' => $this->lease->id, 'due_date' => '2026-10-25',
            'items' => [['type' => 'service', 'description' => 'Kebersihan', 'quantity' => '1', 'unit_price' => '200.000']],
        ])->assertSessionHasNoErrors();

        $balance = fn () => $this->asTenant($this->tenant, fn () => app(OutstandingBalance::class)->forLease(Lease::findOrFail($this->lease->id)));

        $this->assertSame(1_700_000, $balance());

        $this->bindPaid([$this->invoice->id => 500_000]);
        $this->recalc();
        $this->assertSame(1_200_000, $balance());

        $this->actingAs($this->owner)->get(route('owner.leases.show', $this->lease))
            ->assertOk()->assertSee('Sisa tagihan belum dibayar')->assertSee('Rp 1.200.000');

        $manual = $this->invoices()->firstWhere('type.value', 'manual');
        $this->actingAs($this->owner)->post(route('owner.invoices.void', $manual), ['reason' => 'Salah input'])->assertSessionHasNoErrors();
        $this->assertSame(1_000_000, $balance());
    }
}
