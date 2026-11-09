<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoomType> */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' '.fake()->numberBetween(1, 99),
            'base_price' => 1500000,
            'size_m2' => 12.0,
            'facilities' => ['AC'],
            'is_active' => true,
        ];
    }
}
