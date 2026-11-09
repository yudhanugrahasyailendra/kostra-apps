<?php

namespace Database\Factories;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * property_id, room_id, dan resident_id diberikan pemanggil (semuanya milik tenant yang sama).
 *
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => ComplaintCategory::RoomFacility,
            'title' => 'Keluhan '.fake()->word(),
            'description' => fake()->sentence(),
            'status' => ComplaintStatus::New,
        ];
    }
}
