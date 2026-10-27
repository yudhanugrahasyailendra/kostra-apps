<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Berikan tenant, properti, kamar, dan penghuni yang konsisten:
 * Lease::factory()->for($tenant)->for($property)->for($room)->for($resident, 'primaryResident').
 * Untuk kontrak aktif isi status=active dan active_room_id=room_id secara eksplisit.
 *
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => Property::factory(),
            'room_id' => Room::factory(),
            'primary_resident_id' => Resident::factory(),
            'status' => LeaseStatus::Ended,
            'active_room_id' => null,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'billing_cycle' => BillingCycle::Monthly,
            'rent_amount' => 1_500_000,
        ];
    }
}
