<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Kos '.fake()->unique()->streetName(),
            'address' => fake()->streetAddress(),
            'city' => 'Makassar',
            'floors' => 2,
            'is_active' => true,
        ];
    }
}
