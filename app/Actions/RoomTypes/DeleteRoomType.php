<?php

namespace App\Actions\RoomTypes;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRoomType
{
    use ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    public function __invoke(RoomType $type): void
    {
        $tenant = $this->currentTenant();

        DB::transaction(function () use ($tenant, $type) {
            $this->limits->lockTenant($tenant);

            // Kamar terhapus lunak tetap merujuk tipe lewat foreign key, jadi ikut dihitung.
            if (Room::query()->withTrashed()->where('room_type_id', $type->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'general' => 'Tipe ini pernah dipakai kamar. Nonaktifkan saja, jangan dihapus.',
                ]);
            }

            $type->delete();
        });
    }
}
