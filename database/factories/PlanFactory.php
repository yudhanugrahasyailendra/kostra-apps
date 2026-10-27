<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'plan-'.fake()->unique()->numerify('####'),
            'name' => fake()->word(),
            'price_monthly' => 0,
            'price_yearly' => null,
            'max_properties' => 1,
            'max_rooms' => 10,
            'max_users' => 1,
            'monthly_message_quota' => 0,
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['key' => 'free', 'name' => 'Gratis']);
    }
}
