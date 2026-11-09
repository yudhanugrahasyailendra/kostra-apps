<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\LeaseStatus;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pabrik data uji. property_id, room_id, dan primary_resident_id wajib diberikan pemanggil
 * (atau lewat `for`). Kontrak aktif mengisi active_room_id dari room_id; status kamar
 * TIDAK diubah di sini, hanya Action CheckInResident yang melakukannya.
 *
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => LeaseStatus::Active,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->startOfMonth()->addMonths(12)->subDay()->toDateString(),
            'billing_cycle' => BillingCycle::Monthly,
            'rent_amount' => 1500000,
            'checked_in_at' => now(),
        ];
    }

    public function ended(): static
    {
        return $this->state(['status' => LeaseStatus::Ended]);
    }

    /** Isi active_room_id dari room_id setelah dibuat (hanya untuk kontrak aktif). */
    public function configure(): static
    {
        return $this->afterMaking(function (Lease $lease) {
            if ($lease->status === LeaseStatus::Active && $lease->active_room_id === null) {
                $lease->active_room_id = $lease->room_id;
            }
        });
    }
}
