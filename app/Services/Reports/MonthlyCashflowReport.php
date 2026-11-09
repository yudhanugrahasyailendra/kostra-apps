<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

/**
 * Rekap bulanan pemasukan dan pengeluaran. Saldo bersih = pemasukan - pengeluaran (boleh negatif).
 * Memakai IncomeReport dan ExpenseReport, jadi angkanya sama dengan laporan masing-masing.
 */
class MonthlyCashflowReport
{
    public function __construct(private IncomeReport $income, private ExpenseReport $expenses) {}

    /**
     * Bulan terlama lebih dulu, berakhir di $lastMonth.
     *
     * @return list<array{month: string, label: string, income: int, expense: int, net: int}>
     */
    public function months(string $lastMonth, int $count = 6, ?int $propertyId = null): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $lastMonth.'-01', 'UTC');
        $range = new ReportPeriod($end->subMonthsNoOverflow($count - 1)->startOfMonth(), $end->endOfMonth()->startOfDay());

        $income = $this->bucketByMonth($this->income->dailyTotals($range, $propertyId));
        $expense = $this->bucketByMonth($this->expenses->dailyTotals($range, $propertyId));

        $rows = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $month = $end->subMonthsNoOverflow($i);
            $key = $month->format('Y-m');
            $in = $income[$key] ?? 0;
            $out = $expense[$key] ?? 0;

            $rows[] = [
                'month' => $key,
                'label' => $month->locale('id')->translatedFormat('F Y'),
                'income' => $in,
                'expense' => $out,
                'net' => $in - $out,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $daily
     * @return array<string, int>
     */
    private function bucketByMonth(array $daily): array
    {
        $months = [];

        foreach ($daily as $day => $amount) {
            $key = substr($day, 0, 7);
            $months[$key] = ($months[$key] ?? 0) + $amount;
        }

        return $months;
    }
}
