<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Kategori '.fake()->unique()->numberBetween(1, 99999),
            'is_system' => false,
            'sort_order' => 10,
        ];
    }
}
