<?php

namespace Database\Factories;

use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => null,
            'name' => 'Tipe '.fake()->unique()->word(),
            'base_price' => 1_500_000,
            'size_m2' => 12.0,
            'facilities' => ['AC', 'Kamar mandi dalam'],
            'is_active' => true,
        ];
    }
}
