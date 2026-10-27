<?php

namespace Tests\Feature\Finance;

use App\Actions\Leases\CheckOutResident;
use App\Actions\Leases\TransferRoom;
use App\Models\Invoice;
use App\Models\Room;
use App\Services\Reports\ArrearsReport;
use App\Services\Reports\CashFlowReport;
use App\Services\Reports\IncomeReport;
use App\Services\Reports\OccupancyReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFinance();
    }

    private function d(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->tenant->timezone);
    }

    private function income(string $from, string $to, ?int $propertyId = null): int
    {
        return $this->asTenant($this->tenant, fn () => app(IncomeReport::class)->total($this->d($from), $this->d($to), $propertyId));
    }

    private function arrearsRows(string $sort = 'amount'): array
    {
        return $this->asTenant($this->tenant, fn () => app(ArrearsReport::class)->table($this->tenant->today(), null, $sort, [])->rows);
    }

    // ---- Pemasukan ----

    public function test_income_counts_active_payments_by_paid_on_and_ignores_voided_ones(): void
    {
        $invoice = $this->openInvoice();

        $this->record([[$invoice, 500_000]], ['paid_on' => '2026-10-01']);
        $this->record([[$invoice, 400_000]], ['paid_on' => '2026-10-15']);
        $this->record([[$invoice, 300_000]], ['paid_on' => '2026-09-30']);
        $voided = $this->record([[$invoice, 200_000]], ['paid_on' => '2026-10-05']);
        $this->actingAs($this->owner)->post(route('owner.payments.void', $voided), ['reason' => 'Uang belum masuk'])->assertSessionHasNoErrors();

        $this->assertSame(900_000, $this->income('2026-10-01', '2026-10-31'));
        $this->assertSame(300_000, $this->income('2026-09-01', '2026-09-30'));
        $this->assertSame(500_000, $this->income('2026-10-01', '2026-10-01'));
        $this->assertSame(300_000, $this->income('2026-09-30', '2026-09-30'));
        $this->assertSame(0, $this->income('2026-08-01', '2026-08-31'));
        // Total pemasukan = jumlah pembayaran aktif pada periode itu.
        $this->assertSame(1_200_000, $this->income('2026-09-01', '2026-10-31'));
    }

    public function test_income_is_split_per_property_and_can_be_filtered(): void
    {
        $second = $this->propertyFor($this->tenant, ['name' => 'Kos Anggrek']);
        $a = $this->openInvoice();
        $b = $this->openInvoice([], null, $this->leaseFor([], null, $second));
        $this->record([[$a, 700_000]]);
        $this->record([[$b, 1_100_000]]);

        $this->assertSame(1_800_000, $this->income('2026-10-01', '2026-10-31'));
        $this->assertSame(700_000, $this->income('2026-10-01', '2026-10-31', $this->property->id));
        $this->assertSame(1_100_000, $this->income('2026-10-01', '2026-10-31', $second->id));

        $table = $this->asTenant($this->tenant, fn () => app(IncomeReport::class)->table($this->d('2026-10-01'), $this->d('2026-10-31'), null, []));
        $byName = collect($table->rows)->pluck('total', 'property')->all();
        $this->assertEquals([$this->property->name => 700_000, 'Kos Anggrek' => 1_100_000], $byName);
        $this->assertSame(1_800_000, $table->totals['total']);
        $this->assertSame(2, $table->totals['payments']);
    }

    // ---- Tunggakan ----

    public function test_arrears_age_buckets_at_the_boundaries(): void
    {
        $days = [0, 1, 7, 8, 30, 31];
        foreach ($days as $d) {
            $this->invoiceDueDaysAgo($d);
        }

        $rows = collect($this->arrearsRows())->keyBy('days');

        $this->assertSame([1, 7, 8, 30, 31], $rows->keys()->sort()->values()->all(), 'Tagihan yang jatuh temponya hari ini belum menunggak.');
        $this->assertSame(['1-7', '1-7', '8-30', '8-30', '31+'], [
            $rows[1]['bucket'], $rows[7]['bucket'], $rows[8]['bucket'], $rows[30]['bucket'], $rows[31]['bucket'],
        ]);
    }

    public function test_arrears_use_the_remaining_balance_and_skip_paid_and_void_invoices(): void
    {
        $partial = $this->invoiceDueDaysAgo(40);
        $this->record([[$partial, 200_000]]);
        $paid = $this->invoiceDueDaysAgo(10);
        $this->record([[$paid, 1_500_000]]);
        $void = $this->invoiceDueDaysAgo(12);
        $this->asTenant($this->tenant, fn () => Invoice::query()->whereKey($void->id)->update(['status' => 'void']));

        $rows = $this->arrearsRows();

        $this->assertCount(1, $rows);
        $this->assertSame([$partial->number, 1_300_000, '31+'], [$rows[0]['number'], $rows[0]['remaining'], $rows[0]['bucket']]);
        $this->assertSame(1_300_000, $this->asTenant($this->tenant, fn () => app(ArrearsReport::class)->total($this->tenant->today())));
    }

    public function test_arrears_can_be_sorted_by_amount_or_by_age(): void
    {
        $big = $this->invoiceDueDaysAgo(2, [], 3_000_000);
        $old = $this->invoiceDueDaysAgo(40, [], 1_000_000);

        $this->assertSame([$big->number, $old->number], array_column($this->arrearsRows('amount'), 'number'));
        $this->assertSame([$old->number, $big->number], array_column($this->arrearsRows('age'), 'number'));
    }

    public function test_overdue_invoices_not_yet_flagged_by_the_scheduler_still_count(): void
    {
        $invoice = $this->invoiceDueDaysAgo(5);

        $this->assertSame('issued', $invoice->status->value);
        $this->assertCount(1, $this->arrearsRows());
    }

    // ---- Okupansi ----

    public function test_occupancy_follows_the_lease_history_on_each_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 03:00:00', 'UTC'));
        [$tenant, $owner] = [$this->tenant, $this->owner];
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        Room::factory()->for($tenant)->for($this->property)->create(['number' => '900']);

        $this->travelTo(CarbonImmutable::parse('2026-09-01 03:00:00', 'UTC'));
        $this->asTenant($tenant, fn () => app(CheckOutResident::class)($lease, ['checkout_date' => '2026-08-20', 'ended_reason' => 'moved_out']));

        $on = fn (string $date) => $this->asTenant($tenant, fn () => app(OccupancyReport::class)->onDate($tenant, $this->d($date)));

        $this->assertSame([0, 0], [$on('2026-07-31')['total'], $on('2026-07-31')['occupied']]);
        $this->assertSame([2, 1, 1, 50.0], [$on('2026-08-01')['total'], $on('2026-08-01')['occupied'], $on('2026-08-01')['vacant'], $on('2026-08-01')['percent']]);
        $this->assertSame(1, $on('2026-08-19')['occupied']);
        $this->assertSame(0, $on('2026-08-20')['occupied'], 'Pada tanggal check-out kamar sudah kosong.');
        $this->assertSame([2, 0, 0.0], [$on('2026-09-01')['total'], $on('2026-09-01')['occupied'], $on('2026-09-01')['percent']]);
        $this->assertNotNull($owner);
    }

    public function test_average_vacant_days_counts_from_the_last_checkout_or_room_creation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 03:00:00', 'UTC'));
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '900']);

        $this->travelTo(CarbonImmutable::parse('2026-09-01 03:00:00', 'UTC'));
        $this->asTenant($this->tenant, fn () => app(CheckOutResident::class)($lease, ['checkout_date' => '2026-08-20', 'ended_reason' => 'moved_out']));

        $snapshot = $this->asTenant($this->tenant, fn () => app(OccupancyReport::class)->onDate($this->tenant, $this->d('2026-09-01')));

        // Kamar yang check-out 20 Agustus kosong 12 hari; kamar yang tak pernah terisi kosong 31 hari sejak dibuat.
        $this->assertSame(21.5, $snapshot['avg_vacant_days']);
    }

    public function test_a_room_transfer_moves_the_occupancy_between_properties_on_the_transfer_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 03:00:00', 'UTC'));
        $other = $this->propertyFor($this->tenant, ['name' => 'Kos Anggrek']);
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        $target = Room::factory()->for($this->tenant)->for($other)->create(['number' => '700']);

        $this->travelTo(CarbonImmutable::parse('2026-09-01 03:00:00', 'UTC'));
        $this->asTenant($this->tenant, fn () => app(TransferRoom::class)($lease, ['to_room_id' => $target->id, 'transferred_on' => '2026-08-10'], $this->owner));

        $occupiedBy = fn (string $date) => collect($this->asTenant($this->tenant, fn () => app(OccupancyReport::class)->onDate($this->tenant, $this->d($date))['by_property']))
            ->pluck('occupied', 'name')->all();

        $this->assertEquals([$this->property->name => 1, 'Kos Anggrek' => 0], $occupiedBy('2026-08-09'));
        $this->assertEquals([$this->property->name => 0, 'Kos Anggrek' => 1], $occupiedBy('2026-08-10'));
        $this->assertEquals([$this->property->name => 0, 'Kos Anggrek' => 1], $occupiedBy('2026-09-01'));
    }

    public function test_canceled_leases_never_count_as_occupied(): void
    {
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => $lease->update(['status' => 'canceled']));

        $snapshot = $this->asTenant($this->tenant, fn () => app(OccupancyReport::class)->onDate($this->tenant, $this->tenant->today()));

        $this->assertSame([1, 0], [$snapshot['total'], $snapshot['occupied']]);
    }

    // ---- Rekap bulanan ----

    public function test_monthly_summary_net_is_income_minus_expenses(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 300_000]], ['paid_on' => '2026-09-30']);
        $this->record([[$invoice, 900_000]], ['paid_on' => '2026-10-02']);
        $this->expense(['amount' => 250_000, 'spent_on' => '2026-10-03']);
        $this->expense(['amount' => 100_000, 'spent_on' => '2026-10-31', 'property_id' => $this->property->id]);
        $this->expense(['amount' => 50_000, 'spent_on' => '2026-09-01']);
        $deleted = $this->expense(['amount' => 999_000, 'spent_on' => '2026-10-04']);
        $this->asTenant($this->tenant, fn () => $deleted->delete());

        $rows = collect($this->asTenant($this->tenant, fn () => app(CashFlowReport::class)->months($this->d('2026-10-01'), 3)))->keyBy('month');

        $this->assertSame(['2026-08', '2026-09', '2026-10'], $rows->keys()->all());
        $this->assertSame([0, 0, 0], [$rows['2026-08']['income'], $rows['2026-08']['expense'], $rows['2026-08']['net']]);
        $this->assertSame([300_000, 50_000, 250_000], [$rows['2026-09']['income'], $rows['2026-09']['expense'], $rows['2026-09']['net']]);
        $this->assertSame([900_000, 350_000, 550_000], [$rows['2026-10']['income'], $rows['2026-10']['expense'], $rows['2026-10']['net']]);

        $filtered = collect($this->asTenant($this->tenant, fn () => app(CashFlowReport::class)->months($this->d('2026-10-01'), 1, $this->property->id)))->first();
        $this->assertSame([900_000, 100_000, 800_000], [$filtered['income'], $filtered['expense'], $filtered['net']]);
    }

    public function test_a_negative_net_is_kept_as_a_negative_integer(): void
    {
        $this->expense(['amount' => 400_000, 'spent_on' => '2026-10-03']);

        $row = $this->asTenant($this->tenant, fn () => app(CashFlowReport::class)->months($this->d('2026-10-01'), 1)[0]);

        $this->assertSame(-400_000, $row['net']);
    }

    public function test_the_monthly_summary_screen_shows_the_net_balance(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 900_000]], ['paid_on' => '2026-10-02']);
        $this->expense(['amount' => 250_000, 'spent_on' => '2026-10-03']);

        $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'rekap', 'month' => '2026-10']))
            ->assertOk()->assertSee('Saldo bersih')->assertSee('Rp 900.000')->assertSee('Rp 250.000')->assertSee('Rp 650.000');
    }
}
