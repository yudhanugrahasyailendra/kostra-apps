<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Kategori '.fake()->unique()->word(), 'is_system' => false, 'sort_order' => 50];
    }
}
