<?php

namespace Tests\Concerns;

use App\Actions\Leases\CheckInResident;
use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;

trait CreatesTenantFixtures
{
    /**
     * @return array{0: Tenant, 1: User}
     */
    protected function tenantWithOwner(string $plan = 'free'): array
    {
        $this->seedPlans();

        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('key', $plan)->value('id')]);
        $owner = User::factory()->for($tenant)->create();

        return [$tenant, $owner];
    }

    protected function propertyFor(Tenant $tenant, array $attributes = []): Property
    {
        return Property::factory()->for($tenant)->create($attributes);
    }

    /**
     * Check-in lewat Action (jalur yang sama dengan UI). Tanggal bawaan: mulai 2 bulan lalu, berakhir 10 bulan lagi.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function checkIn(Tenant $tenant, Room $room, Resident $resident, array $extra = []): Lease
    {
        return $this->asTenant($tenant, fn () => app(CheckInResident::class)(array_merge([
            'room_id' => $room->id,
            'primary_resident_id' => $resident->id,
            'start_date' => $tenant->today()->subMonths(2)->toDateString(),
            'end_date' => $tenant->today()->addMonths(10)->toDateString(),
            'deposit_amount' => 500_000,
            'deposit_received_on' => $tenant->today()->subMonths(2)->toDateString(),
        ], $extra)));
    }
}
