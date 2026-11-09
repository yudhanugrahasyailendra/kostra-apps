<?php

namespace App\Services\Reports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Pengeluaran (yang di-soft-delete tidak dihitung) menurut `spent_on`. Filter properti hanya memuat pengeluaran properti itu. */
class ExpenseReport
{
    /** @return Builder<Expense> */
    public function query(ReportPeriod $period, ?int $propertyId = null, ?int $categoryId = null): Builder
    {
        return Expense::query()
            ->whereBetween('spent_on', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($propertyId, fn (Builder $q) => $q->where('property_id', $propertyId))
            ->when($categoryId, fn (Builder $q) => $q->where('expense_category_id', $categoryId));
    }

    public function total(ReportPeriod $period, ?int $propertyId = null, ?int $categoryId = null): int
    {
        return (int) $this->query($period, $propertyId, $categoryId)->sum('amount');
    }

    /** @return Collection<int, array<string, scalar|null>> */
    public function rows(ReportPeriod $period, ?int $propertyId = null, ?int $categoryId = null): Collection
    {
        return $this->query($period, $propertyId, $categoryId)
            ->with(['category', 'property'])
            ->orderBy('spent_on')->orderBy('id')
            ->get()
            ->map(fn (Expense $expense) => [
                'spent_on' => $expense->spent_on->toDateString(),
                'category_name' => $expense->category->name,
                'description' => $expense->description,
                'vendor_name' => $expense->vendor_name,
                'property_name' => $expense->property?->name ?? 'Umum (semua properti)',
                'amount' => $expense->amount,
            ]);
    }

    /** @return array<string, int> Kunci Y-m-d. */
    public function dailyTotals(ReportPeriod $period, ?int $propertyId = null): array
    {
        return $this->query($period, $propertyId)
            ->groupBy('spent_on')
            ->selectRaw('spent_on as day, SUM(amount) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [substr((string) $row->getRawOriginal('day'), 0, 10) => (int) $row->total])
            ->all();
    }
}
