<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Actions\Leases\RenewLease;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceIssued;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\NumberSequence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BillingFixtures;
use Tests\TestCase;

class GenerateMonthlyInvoicesTest extends TestCase
{
    use BillingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    public function test_issues_one_invoice_per_active_lease_with_the_expected_fields(): void
    {
        $lease = $this->leaseFor();

        $result = $this->generate();

        $this->assertSame([1, 0, 0], [$result->issued, $result->skipped, $result->failed]);

        $invoice = $this->invoices()->sole();
        $this->assertSame('INV-2026-10-0001', $invoice->number);
        $this->assertSame(InvoiceType::Recurring, $invoice->type);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('2026-10', $invoice->period);
        $this->assertSame('2026-10-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-10-31', $invoice->period_end->toDateString());
        $this->assertSame('2026-10-15', $invoice->issue_date->toDateString());
        $this->assertSame($lease->id, $invoice->lease_id);
        $this->assertSame($lease->primary_resident_id, $invoice->resident_id);
        $this->assertSame($lease->property_id, $invoice->property_id);
        $this->assertSame($this->tenant->id, $invoice->tenant_id);
        $this->assertNull($invoice->created_by);
        $this->assertNotNull($invoice->issued_at);
        $this->assertSame([1_500_000, 0, 1_500_000, 0], [$invoice->subtotal, $invoice->adjustment_total, $invoice->total, $invoice->paid_total]);

        $item = $invoice->items->sole();
        $this->assertSame(InvoiceItemType::Rent, $item->type);
        $this->assertSame('1.00', $item->quantity);
        $this->assertSame([1_500_000, 1_500_000], [$item->unit_price, $item->amount]);
        $this->assertSame('Sewa kamar 100 - Oktober 2026', $item->description);
    }

    public function test_running_twice_on_the_same_day_creates_no_duplicates(): void
    {
        $this->leaseFor();
        $this->leaseFor();

        $first = $this->generate();
        $second = $this->generate();

        $this->assertSame(2, $first->issued);
        $this->assertSame([0, 2, 0], [$second->issued, $second->skipped, $second->failed]);
        $this->assertSame(2, $this->invoices()->count());
        $this->assertSame(['INV-2026-10-0001', 'INV-2026-10-0002'], $this->invoices()->pluck('number')->all());
        $this->assertSame(2, $this->asTenant($this->tenant, fn () => NumberSequence::firstOrFail()->last_number));
    }

    public function test_one_hundred_leases_get_one_hundred_invoices_and_a_rerun_adds_none(): void
    {
        foreach (range(1, 100) as $i) {
            $this->leaseFor();
        }

        $first = $this->generate();
        $this->assertSame(100, $first->issued);
        $this->assertSame(100, $this->invoices()->count());

        $second = $this->generate();
        $this->assertSame([0, 100, 0], [$second->issued, $second->skipped, $second->failed]);
        $this->assertSame(100, $this->invoices()->count());

        // Nomor 1..100 tanpa celah dan tanpa duplikat.
        $numbers = $this->invoices()->pluck('number')->sort()->values();
        $this->assertSame(100, $numbers->unique()->count());
        $this->assertSame('INV-2026-10-0001', $numbers->first());
        $this->assertSame('INV-2026-10-0100', $numbers->last());
    }

    public function test_the_database_rejects_a_second_monthly_invoice_for_the_same_lease_and_period(): void
    {
        $this->leaseFor();
        $this->generate();
        $existing = $this->invoices()->sole();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->asTenant($this->tenant, fn () => Invoice::create([
            'property_id' => $existing->property_id, 'lease_id' => $existing->lease_id, 'resident_id' => $existing->resident_id,
            'number' => 'INV-2026-10-9999', 'type' => InvoiceType::Recurring, 'period' => '2026-10',
            'issue_date' => '2026-10-15', 'due_date' => '2026-10-20', 'status' => InvoiceStatus::Issued,
        ]));
    }

    public function test_manual_invoices_without_period_do_not_collide_with_each_other(): void
    {
        $lease = $this->leaseFor();

        foreach ([1, 2] as $n) {
            $this->asTenant($this->tenant, fn () => Invoice::create([
                'property_id' => $lease->property_id, 'lease_id' => $lease->id, 'resident_id' => $lease->primary_resident_id,
                'number' => 'INV-2026-10-900'.$n, 'type' => InvoiceType::Manual, 'period' => null,
                'issue_date' => '2026-10-15', 'due_date' => '2026-10-20', 'status' => InvoiceStatus::Issued,
            ]));
        }

        $this->assertSame(2, $this->invoices()->count());
    }

    public function test_due_date_rules(): void
    {
        // Hari ini tanggal 2, jatuh tempo bawaan tanggal 10, kontrak kedua tanggal 5.
        $this->travelTo('2026-10-02 03:00:00');
        $default = $this->leaseFor(['start_date' => '2026-01-01']);
        $custom = $this->leaseFor(['start_date' => '2026-01-01', 'due_day' => 5]);

        $this->generate();

        $due = $this->invoices()->keyBy('lease_id');
        $this->assertSame('2026-10-10', $due[$default->id]->due_date->toDateString());
        $this->assertSame('2026-10-05', $due[$custom->id]->due_date->toDateString());
    }

    public function test_a_new_invoice_is_never_born_overdue(): void
    {
        // Hari ini tanggal 15, jatuh tempo bawaan tanggal 10: dimajukan ke tanggal terbit.
        $this->leaseFor();

        $this->generate();

        $invoice = $this->invoices()->sole();
        $this->assertSame('2026-10-15', $invoice->due_date->toDateString());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_nothing_is_issued_before_the_issue_day_but_missed_days_are_caught_up(): void
    {
        $this->billingSettings(['issue_day' => 20]);
        $this->leaseFor();

        $this->assertSame(0, $this->generate()->issued);
        $this->assertSame(0, $this->invoices()->count());

        $this->travelTo('2026-10-20 03:00:00');
        $this->assertSame(1, $this->generate()->issued);

        // Terlewat beberapa hari: tetap tersusul, dan tidak ganda.
        $this->travelTo('2026-10-27 03:00:00');
        $this->assertSame(0, $this->generate()->issued);
        $this->assertSame(1, $this->invoices()->count());
    }

    public function test_a_lease_checked_in_after_the_issue_day_is_billed_on_the_next_run(): void
    {
        $this->generate();
        $this->assertSame(0, $this->invoices()->count());

        $this->leaseFor(['start_date' => '2026-10-15', 'end_date' => null]);

        $result = $this->generate();

        $this->assertSame(1, $result->issued);
        $this->assertSame('2026-10', $this->invoices()->sole()->period);
        $this->assertSame(0, $this->generate()->issued);
    }

    public function test_only_the_current_month_is_billed_automatically_and_backfill_is_explicit(): void
    {
        $this->leaseFor(['start_date' => '2026-08-01']);

        $this->generate();
        $this->assertSame(['2026-10'], $this->invoices()->pluck('period')->all());

        $this->assertSame(1, $this->generate('2026-09')->issued);
        $this->assertSame(1, $this->generate('2026-08')->issued);
        $this->assertSame(0, $this->generate('2026-09')->issued);

        $this->assertEqualsCanonicalizing(['2026-10', '2026-09', '2026-08'], $this->invoices()->pluck('period')->all());

        // Pengisian ulang periode lampau tidak dimajukan jatuh temponya: memang sudah lewat.
        $backfilled = $this->invoices()->firstWhere('period', '2026-09');
        $this->assertSame('2026-09-10', $backfilled->due_date->toDateString());
        $this->assertSame('2026-10-15', $backfilled->issue_date->toDateString());
    }

    public function test_backfill_ignores_the_issue_day_gate(): void
    {
        $this->billingSettings(['issue_day' => 28]);
        $this->leaseFor();

        $this->assertSame(0, $this->generate()->issued);
        $this->assertSame(1, $this->generate('2026-09')->issued);
    }

    public function test_ended_leases_and_leases_that_finished_before_the_month_are_not_billed(): void
    {
        $active = $this->leaseFor();
        $finishedBefore = $this->leaseFor(['end_date' => '2026-09-30']);
        $checkedOut = $this->leaseFor();
        $this->actingAs($this->owner)->post(route('owner.leases.check-out', $checkedOut), [
            'checkout_date' => '2026-10-10', 'ended_reason' => 'moved_out',
        ])->assertSessionHasNoErrors();
        $openEnded = $this->leaseFor(['end_date' => null]);

        $this->generate();

        $billed = $this->invoices()->pluck('lease_id')->all();
        $this->assertEqualsCanonicalizing([$active->id, $openEnded->id], $billed);
        $this->assertNotContains($finishedBefore->id, $billed);
        $this->assertNotContains($checkedOut->id, $billed);
    }

    #[DataProvider('prorations')]
    public function test_pro_rata_amounts_for_every_month_length(
        string $today,
        string $start,
        ?string $end,
        bool $first,
        bool $last,
        int $expectedAmount,
        string $expectedStart,
        string $expectedEnd,
        ?string $expectedLabel,
    ): void {
        $this->travelTo($today.' 03:00:00');
        $this->billingSettings(['prorate_first_month' => $first, 'prorate_last_month' => $last]);
        $this->leaseFor(['start_date' => $start, 'end_date' => $end]);

        $this->generate();

        $invoice = $this->invoices()->sole();
        $this->assertSame($expectedAmount, $invoice->total);
        $this->assertSame($expectedAmount, $invoice->items->sole()->amount);
        $this->assertSame($expectedStart, $invoice->period_start->toDateString());
        $this->assertSame($expectedEnd, $invoice->period_end->toDateString());

        if ($expectedLabel === null) {
            $this->assertStringNotContainsString('pro rata', $invoice->items->sole()->description);
        } else {
            $this->assertStringContainsString($expectedLabel, $invoice->items->sole()->description);
        }
    }

    public static function prorations(): array
    {
        return [
            'bulan 31 hari, mulai tanggal 12' => ['2026-10-20', '2026-10-12', null, true, true, 967_742, '2026-10-12', '2026-10-31', 'pro rata 20 dari 31 hari'],
            'bulan 30 hari, mulai tanggal 16' => ['2026-04-20', '2026-04-16', null, true, true, 750_000, '2026-04-16', '2026-04-30', 'pro rata 15 dari 30 hari'],
            'februari 28 hari, mulai tanggal 15' => ['2027-02-20', '2027-02-15', null, true, true, 750_000, '2027-02-15', '2027-02-28', 'pro rata 14 dari 28 hari'],
            'februari kabisat 29 hari' => ['2028-02-20', '2028-02-15', null, true, true, 775_862, '2028-02-15', '2028-02-29', 'pro rata 15 dari 29 hari'],
            'berakhir tengah bulan' => ['2026-10-20', '2026-01-01', '2026-10-10', true, true, 483_871, '2026-10-01', '2026-10-10', 'pro rata 10 dari 31 hari'],
            'mulai dan berakhir di bulan yang sama' => ['2026-10-20', '2026-10-05', '2026-10-20', true, true, 774_194, '2026-10-05', '2026-10-20', 'pro rata 16 dari 31 hari'],
            'pro rata mati: bulan pertama penuh' => ['2026-10-20', '2026-10-12', null, false, false, 1_500_000, '2026-10-01', '2026-10-31', null],
            'pro rata mati: bulan terakhir penuh' => ['2026-10-20', '2026-01-01', '2026-10-10', false, false, 1_500_000, '2026-10-01', '2026-10-31', null],
            'hanya awal aktif' => ['2026-10-20', '2026-10-12', '2026-10-25', true, false, 967_742, '2026-10-12', '2026-10-31', 'pro rata 20 dari 31 hari'],
            'hanya akhir aktif' => ['2026-10-20', '2026-10-12', '2026-10-25', false, true, 1_209_677, '2026-10-01', '2026-10-25', 'pro rata 25 dari 31 hari'],
            'berakhir tepat akhir bulan' => ['2026-10-20', '2026-01-01', '2026-10-31', true, true, 1_500_000, '2026-10-01', '2026-10-31', null],
        ];
    }

    public function test_renewal_in_the_middle_of_a_month_bills_each_day_once_with_pro_rata(): void
    {
        $this->travelTo('2026-10-01 03:00:00');
        $this->billingSettings(['prorate_first_month' => true, 'prorate_last_month' => true]);
        $old = $this->leaseFor(['end_date' => '2026-10-15']);

        $this->generate();
        $this->assertSame(725_806, $this->invoices()->sole()->total); // 15 dari 31 hari

        $this->travelTo('2026-10-16 03:00:00');
        $new = $this->asTenant($this->tenant, fn () => app(RenewLease::class)($old, ['end_date' => '2027-03-31']));
        $this->assertSame('2026-10-16', $new->start_date->toDateString());

        $result = $this->generate();

        $this->assertSame(1, $result->issued);
        $invoices = $this->invoices()->keyBy('lease_id');
        $this->assertSame(774_194, $invoices[$new->id]->total); // 16 dari 31 hari
        $this->assertSame('2026-10-16', $invoices[$new->id]->period_start->toDateString());
        $this->assertSame(1_500_000, $invoices->sum('total'), 'Dua potongan bulan harus tepat satu bulan sewa.');
    }

    public function test_renewal_in_the_middle_of_a_month_is_not_billed_twice_without_pro_rata(): void
    {
        $this->travelTo('2026-10-01 03:00:00');
        $old = $this->leaseFor(['end_date' => '2026-10-15']);

        $this->generate();
        $this->assertSame(1_500_000, $this->invoices()->sole()->total);

        $this->travelTo('2026-10-16 03:00:00');
        $new = $this->asTenant($this->tenant, fn () => app(RenewLease::class)($old, ['end_date' => '2027-03-31']));

        $result = $this->generate();

        $this->assertSame([0, 1], [$result->issued, $result->skipped]);
        $this->assertSame(1, $this->invoices()->count());
        $this->assertNotContains($new->id, $this->invoices()->pluck('lease_id')->all());

        // Bulan berikutnya kontrak baru ditagih normal.
        $this->travelTo('2026-11-02 03:00:00');
        $this->assertSame(1, $this->generate()->issued);
        $this->assertSame($new->id, $this->invoices()->last()->lease_id);
    }

    public function test_a_voided_predecessor_invoice_does_not_block_the_renewed_lease(): void
    {
        $this->travelTo('2026-10-01 03:00:00');
        $old = $this->leaseFor(['end_date' => '2026-10-15']);
        $this->generate();
        $this->asTenant($this->tenant, fn () => Invoice::firstOrFail()->update(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => 'salah terbit']));

        $this->travelTo('2026-10-16 03:00:00');
        $new = $this->asTenant($this->tenant, fn () => app(RenewLease::class)($old, ['end_date' => '2027-03-31']));

        $this->assertSame(1, $this->generate()->issued);
        $this->assertContains($new->id, $this->invoices()->pluck('lease_id')->all());
    }

    public function test_one_failing_lease_does_not_stop_the_others_and_numbers_stay_gapless(): void
    {
        $fail = true;
        InvoiceItem::creating(function (InvoiceItem $item) use (&$fail) {
            if ($fail && str_contains($item->description, 'kamar 101')) {
                throw new RuntimeException('Gagal buatan: nama@contoh.test');
            }
        });

        $a = $this->leaseFor();   // kamar 100
        $b = $this->leaseFor();   // kamar 101 (gagal)
        $c = $this->leaseFor();   // kamar 102

        $result = $this->generate();

        $this->assertSame([2, 1], [$result->issued, $result->failed]);
        $this->assertSame([['lease_id' => $b->id, 'error' => RuntimeException::class]], $result->failures);
        $this->assertStringNotContainsString('contoh.test', json_encode($result->failures));

        // Nomor kontrak yang gagal dikembalikan: 0001 dan 0002 berurutan, tanpa lubang.
        $this->assertSame(['INV-2026-10-0001', 'INV-2026-10-0002'], $this->invoices()->pluck('number')->all());
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $this->invoices()->pluck('lease_id')->all());

        // Setelah pulih, kontrak yang tertinggal tertagih, nomornya melanjutkan tanpa celah.
        $fail = false;
        $retry = $this->generate();
        $this->assertSame([1, 2, 0], [$retry->issued, $retry->skipped, $retry->failed]);
        $this->assertSame(['INV-2026-10-0001', 'INV-2026-10-0002', 'INV-2026-10-0003'], $this->invoices()->pluck('number')->all());
    }

    public function test_issued_event_fires_once_per_new_invoice_only(): void
    {
        Event::fake([InvoiceIssued::class]);
        $this->leaseFor();
        $this->leaseFor();

        $this->generate();
        Event::assertDispatchedTimes(InvoiceIssued::class, 2);

        $this->generate();
        Event::assertDispatchedTimes(InvoiceIssued::class, 2);
        Event::assertDispatched(InvoiceIssued::class, fn (InvoiceIssued $e) => $e->tenantId === $this->tenant->id
            && $this->invoices()->pluck('id')->contains($e->invoiceId));
    }

    public function test_each_tenant_has_its_own_numbering_and_is_billed_in_its_own_context(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        User::factory()->for($other)->create();
        $this->leaseFor();
        $this->leaseFor([], $other);

        $this->generate();

        $this->assertSame(0, $this->invoices($other)->count(), 'Menjalankan tenant A tidak boleh menagih tenant B.');

        $this->generate(null, $other);

        $this->assertSame('INV-2026-10-0001', $this->invoices()->sole()->number);
        $this->assertSame('INV-2026-10-0001', $this->invoices($other)->sole()->number);
        $this->assertSame(2, $this->withoutTenant(fn () => Invoice::count()));
    }

    public function test_custom_invoice_prefix_is_used(): void
    {
        $this->billingSettings(['invoice_prefix' => 'KOS']);
        $this->leaseFor();

        $this->generate();

        $this->assertSame('KOS-2026-10-0001', $this->invoices()->sole()->number);
    }

    public function test_money_columns_are_integers_never_floats(): void
    {
        $this->leaseFor(['rent_amount' => 1_234_567]);
        $this->generate();

        $invoice = $this->invoices()->sole();
        foreach (['subtotal', 'adjustment_total', 'total', 'paid_total'] as $column) {
            $this->assertIsInt($invoice->{$column}, $column);
        }

        foreach (Schema::getColumns('invoices') as $column) {
            if (in_array($column['name'], ['subtotal', 'adjustment_total', 'total', 'paid_total'], true)) {
                $this->assertStringContainsString('bigint', $column['type_name']);
            }
        }
    }

    public function test_the_action_requires_a_tenant_context(): void
    {
        $this->expectException(\LogicException::class);

        app(GenerateMonthlyInvoices::class)();
    }
}
