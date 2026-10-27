<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Selalu berikan tenant yang sama untuk kategori dan pembuat: Expense::factory()->for($tenant)
 * ->for($category, 'category')->for($owner, 'creator').
 *
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => null,
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => 250_000,
            'spent_on' => now()->toDateString(),
            'description' => 'Pengeluaran '.fake()->word(),
            'vendor_name' => null,
            'complaint_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
