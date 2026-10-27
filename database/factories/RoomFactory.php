<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Selalu berikan tenant dan properti yang sama: Room::factory()->for($tenant)->for($property).
 *
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => Property::factory(),
            'room_type_id' => null,
            // Berawalan F supaya tidak pernah bentrok dengan nomor angka yang dipakai tes.
            'number' => 'F'.fake()->unique()->numberBetween(100, 99999),
            'floor' => 1,
            'price' => 1_500_000,
            'status' => RoomStatus::Vacant,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => RoomStatus::Occupied]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['status' => RoomStatus::Maintenance]);
    }
}
