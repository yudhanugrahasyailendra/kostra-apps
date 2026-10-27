<?php

namespace Database\Factories;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Berikan tenant, properti, kamar, dan penghuni yang konsisten:
 * Complaint::factory()->for($tenant)->for($property)->for($room)->for($resident).
 *
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => Property::factory(),
            'room_id' => Room::factory(),
            'resident_id' => Resident::factory(),
            'category' => ComplaintCategory::RoomFacility,
            'title' => 'Keluhan '.fake()->word(),
            'description' => 'Deskripsi keluhan '.fake()->sentence(),
            'status' => ComplaintStatus::New,
            'assigned_to' => null,
        ];
    }
}
