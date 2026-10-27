<?php

namespace Tests\Concerns;

use App\Actions\Finance\SeedExpenseCategories;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;

/**
 * Fixture keuangan di atas fixture pembayaran. Tanggal dibekukan lewat setUpBilling.
 */
trait FinanceFixtures
{
    use PaymentFixtures;

    protected function setUpFinance(string $today = '2026-10-15', string $plan = 'business'): void
    {
        $this->setUpBilling($today, $plan);
        $this->asTenant($this->tenant, fn () => app(SeedExpenseCategories::class)());
    }

    protected function category(string $name = 'Listrik', ?Tenant $tenant = null): ExpenseCategory
    {
        $tenant ??= $this->tenant;

        return $this->asTenant($tenant, function () use ($name) {
            app(SeedExpenseCategories::class)();

            return ExpenseCategory::query()->where('name', $name)->firstOrFail();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function expense(array $attributes = [], ?Tenant $tenant = null, ?User $by = null): Expense
    {
        $tenant ??= $this->tenant;
        $by ??= $tenant->is($this->tenant) ? $this->owner : User::factory()->for($tenant)->create();
        $category = $this->category('Listrik', $tenant);

        return $this->asTenant($tenant, fn () => Expense::factory()->for($tenant)->for($category, 'category')->for($by, 'creator')->create($attributes));
    }

    protected function staffWith(array $permissions = [], ?Tenant $tenant = null): User
    {
        return User::factory()->for($tenant ?? $this->tenant)->staff($permissions)->create();
    }

    /**
     * Tagihan terbuka yang jatuh temponya $days hari sebelum hari ini (negatif = belum jatuh tempo).
     */
    protected function invoiceDueDaysAgo(int $days, array $leaseExtra = [], ?int $total = null): Invoice
    {
        $invoice = $this->openInvoice($leaseExtra);
        $due = $this->tenant->today()->subDays($days)->toDateString();
        $attributes = ['due_date' => $due];

        if ($total !== null) {
            $attributes += ['subtotal' => $total, 'total' => $total];
        }

        $this->asTenant($this->tenant, fn () => Invoice::query()->whereKey($invoice->getKey())->update($attributes));

        return $this->fresh($invoice);
    }
}
