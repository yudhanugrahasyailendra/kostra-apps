<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Kolom key maksimal 30 karakter; slug acak dari faker bisa lebih panjang.
            'key' => 'plan-'.Str::lower(Str::random(12)),
            'name' => fake()->word(),
            'price_monthly' => 0,
            'price_yearly' => null,
            'max_properties' => 1,
            'max_rooms' => 10,
            'max_users' => null,
            'monthly_message_quota' => 0,
            // Semua fitur menyala supaya tes fitur lain tidak tersandung; tes pembatasan memakai ->state(['features' => []]).
            'features' => Plan::FEATURES,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
