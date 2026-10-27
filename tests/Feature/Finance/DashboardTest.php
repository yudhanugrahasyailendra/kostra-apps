<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\Room;
use App\Support\Rupiah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFinance();
    }

    private function seedMoney(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 900_000]], ['paid_on' => '2026-10-02']);
        $voided = $this->record([[$invoice, 100_000]], ['paid_on' => '2026-10-03']);
        $this->actingAs($this->owner)->post(route('owner.payments.void', $voided), ['reason' => 'Salah catat']);
        $this->invoiceDueDaysAgo(40, [], 2_000_000);
        $this->invoiceDueDaysAgo(3, [], 500_000);
    }

    public function test_dashboard_figures_match_the_reports_for_the_same_period(): void
    {
        $this->seedMoney();

        $dashboard = $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk();
        // Dihitung terpisah dari database, bukan dari kelas laporan.
        $received = (int) DB::table('payments')->where('tenant_id', $this->tenant->id)->where('status', 'active')
            ->whereBetween('paid_on', ['2026-10-01', '2026-10-31'])->sum('amount');
        $outstanding = (int) DB::table('invoices')->where('tenant_id', $this->tenant->id)->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where('due_date', '<', '2026-10-15')->selectRaw('SUM(total - paid_total) as s')->value('s');

        $this->assertSame(900_000, $received);
        $dashboard->assertSee('data-stat="income">'.Rupiah::format($received).'</div>', false);
        $dashboard->assertSee('data-stat="arrears">'.Rupiah::format($outstanding).'</div>', false);

        $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'pemasukan', 'month' => '2026-10']))
            ->assertSee(Rupiah::format($received));
        $this->actingAs($this->owner)->get(route('owner.reports.show', 'tunggakan'))
            ->assertSee(Rupiah::format($outstanding));
    }

    public function test_dashboard_shows_occupancy_and_expiring_leases(): void
    {
        $this->leaseFor(['start_date' => '2026-09-01', 'end_date' => '2026-10-25']);
        $this->leaseFor(['start_date' => '2026-09-01', 'end_date' => '2026-12-31']);

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk()
            ->assertSee('data-stat="occupancy">100,0%</div>', false)
            ->assertSee('data-stat="expiring">1</div>', false)
            ->assertSee('2 dari 2 kamar terisi');
    }

    public function test_the_chart_has_a_table_alternative(): void
    {
        $this->seedMoney();

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk()
            ->assertSee('Lihat sebagai tabel')->assertSee('Saldo bersih')->assertSee('data-tip', false);
    }

    public function test_staff_without_finance_view_do_not_see_money_on_the_dashboard(): void
    {
        $this->seedMoney();
        $plain = $this->staffWith();

        $this->actingAs($plain)->get(route('owner.dashboard'))->assertOk()
            ->assertSee('data-stat="occupancy"', false)
            ->assertSee('data-stat="expiring"', false)
            ->assertDontSee('data-stat="income"', false)
            ->assertDontSee('data-stat="arrears"', false)
            ->assertDontSee('Pemasukan dan pengeluaran');

        $this->actingAs($this->staffWith(['finance.view']))->get(route('owner.dashboard'))
            ->assertSee('data-stat="income"', false)->assertSee('data-stat="arrears"', false);
    }

    public function test_another_tenants_money_never_reaches_the_dashboard(): void
    {
        $this->seedMoney();
        [$other] = $this->tenantWithOwner('business');
        $property = $this->propertyFor($other);
        $lease = $this->leaseFor([], $other, $property);
        $this->generate(null, $other);
        $invoice = $this->asTenant($other, fn () => Invoice::where('lease_id', $lease->id)->firstOrFail());
        $this->record([[$invoice, 1_500_000]], [], null, $other);

        $this->actingAs($this->owner)->get(route('owner.dashboard'))
            ->assertSee('data-stat="income">Rp 900.000</div>', false);
    }

    public function test_a_hundred_rooms_load_the_dashboard_quickly_with_a_bounded_number_of_queries(): void
    {
        $this->asTenant($this->tenant, function () {
            for ($i = 1; $i <= 100; $i++) {
                $room = Room::factory()->for($this->tenant)->for($this->property)->create(['number' => 'P'.$i]);
                $resident = Resident::factory()->for($this->tenant)->create();
                $lease = Lease::factory()->for($this->tenant)->for($this->property)->for($room)->for($resident, 'primaryResident')
                    ->create(['status' => 'active', 'active_room_id' => $room->id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
                Invoice::factory()->for($this->tenant)->for($this->property)->for($lease)->for($resident, 'resident')
                    ->create(['number' => 'PERF-'.$i, 'due_date' => '2026-09-01', 'period' => null, 'status' => 'overdue']);
            }
        });

        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);

        $this->actingAs($this->owner)->get(route('owner.dashboard'))
            ->assertOk()->assertSee('data-stat="occupancy">100,0%</div>', false);

        $elapsed = microtime(true) - $start;
        $queries = count(DB::getQueryLog());

        $this->assertLessThan(2.0, $elapsed, 'Dashboard 100 kamar harus terbuka kurang dari 2 detik.');
        $this->assertLessThan(40, $queries, "Jumlah query dashboard membengkak ({$queries}); periksa N+1.");
    }
}
