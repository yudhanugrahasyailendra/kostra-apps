<?php

namespace App\Services\Reports;

use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rekap bulanan: pemasukan (IncomeReport, paid_on) dikurangi pengeluaran (spent_on) menjadi saldo bersih.
 * Dengan filter properti, pengeluaran umum (tanpa properti) tidak ikut dihitung.
 */
class CashFlowReport
{
    public function __construct(private readonly IncomeReport $income) {}

    /**
     * Daftar bulan berurutan naik, $count bulan berakhir pada bulan $endMonth.
     *
     * @return list<array{month: string, income: int, expense: int, net: int}>
     */
    public function months(CarbonImmutable $endMonth, int $count = 12, ?int $propertyId = null): array
    {
        $last = $endMonth->startOfMonth();
        $first = $last->subMonths($count - 1);

        $income = $this->income->byDay($first, $last->endOfMonth(), $propertyId);

        $expenses = Expense::query()
            ->whereBetween('spent_on', [$first->toDateString(), $last->endOfMonth()->toDateString()])
            ->when($propertyId, fn (Builder $query) => $query->where('property_id', $propertyId))
            ->selectRaw('spent_on as day, SUM(amount) as total')
            ->groupBy('spent_on')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day) => [substr((string) $day, 0, 10) => (int) $total]);

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $month = $first->addMonths($i)->format('Y-m');
            $in = $this->sumMonth($income, $month);
            $out = $this->sumMonth($expenses->all(), $month);

            $rows[] = ['month' => $month, 'income' => $in, 'expense' => $out, 'net' => $in - $out];
        }

        return $rows;
    }

    public function table(CarbonImmutable $endMonth, int $count, ?int $propertyId, array $meta): ReportTable
    {
        $rows = array_map(fn (array $row) => [
            'month' => $row['month'].'-01',
            'income' => $row['income'],
            'expense' => $row['expense'],
            'net' => $row['net'],
        ], $this->months($endMonth, $count, $propertyId));

        return new ReportTable(
            key: 'rekap-bulanan',
            title: 'Rekap pemasukan dan pengeluaran bulanan',
            meta: $meta,
            columns: [
                ['key' => 'month', 'label' => 'Bulan', 'type' => 'month'],
                ['key' => 'income', 'label' => 'Pemasukan', 'type' => 'money'],
                ['key' => 'expense', 'label' => 'Pengeluaran', 'type' => 'money'],
                ['key' => 'net', 'label' => 'Saldo bersih', 'type' => 'money'],
            ],
            rows: $rows,
            totals: [
                'month' => 'Total',
                'income' => array_sum(array_column($rows, 'income')),
                'expense' => array_sum(array_column($rows, 'expense')),
                'net' => array_sum(array_column($rows, 'net')),
            ],
        );
    }

    /**
     * @param  array<string, int>  $byDay
     */
    private function sumMonth(array $byDay, string $month): int
    {
        $sum = 0;

        foreach ($byDay as $day => $amount) {
            if (str_starts_with((string) $day, $month)) {
                $sum += $amount;
            }
        }

        return $sum;
    }
}
