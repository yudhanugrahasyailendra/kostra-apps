<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Kos '.fake()->unique()->lastName(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'floors' => 2,
            'is_active' => true,
        ];
    }
}
