<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * created_by diberikan pemanggil. Kategori dibuat baru bila tidak diberikan.
 *
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => 250000,
            'spent_on' => now()->toDateString(),
            'description' => 'Pengeluaran '.fake()->word(),
        ];
    }
}
