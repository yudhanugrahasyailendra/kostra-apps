<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resident> */
class ResidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+62812'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'occupation' => fake()->jobTitle(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
