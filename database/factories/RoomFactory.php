<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * property_id wajib diberikan lewat state/for (kamar selalu milik satu properti).
 *
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(100, 9999),
            'floor' => 1,
            'price' => 1500000,
            'status' => RoomStatus::Vacant,
        ];
    }
}
