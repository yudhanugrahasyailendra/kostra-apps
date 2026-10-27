<?php

namespace App\Actions\Rooms;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\Room;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRoom
{
    use ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    public function __invoke(Room $room): void
    {
        $tenant = $this->currentTenant();

        DB::transaction(function () use ($tenant, $room) {
            $this->limits->lockTenant($tenant);

            $room->refresh();

            if ($room->isOccupied()) {
                throw ValidationException::withMessages([
                    'general' => 'Kamar yang sedang terisi tidak bisa dihapus. Selesaikan kontraknya lebih dulu.',
                ]);
            }

            $room->delete();
        });
    }
}
