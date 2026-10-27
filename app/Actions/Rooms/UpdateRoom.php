<?php

namespace App\Actions\Rooms;

use App\Actions\Concerns\ResolvesTenant;
use App\Actions\Rooms\Concerns\ChecksRoomRules;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRoom
{
    use ChecksRoomRules, ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * Properti tidak bisa diganti. Status terisi tidak bisa diatur manual, dan kamar
     * yang sedang terisi tidak bisa dipindah ke status lain.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Room $room, array $data): Room
    {
        $tenant = $this->currentTenant();

        return DB::transaction(function () use ($tenant, $room, $data) {
            $this->limits->lockTenant($tenant);

            $room->refresh();

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $status = RoomStatus::from($data['status']);

                if ($status === RoomStatus::Occupied || ($room->isOccupied() && $status !== RoomStatus::Occupied)) {
                    throw ValidationException::withMessages(['status' => 'Status terisi ditentukan oleh kontrak, bukan diatur manual.']);
                }
            }

            $property = $room->property()->firstOrFail();
            $update = [];

            if (array_key_exists('number', $data)) {
                $number = trim((string) $data['number']);
                $this->ensureNumbersAvailable($property, [$number], $room->getKey());
                $update['number'] = $number;
            }

            if (array_key_exists('room_type_id', $data)) {
                $update['room_type_id'] = $this->resolveRoomType($property, $data['room_type_id'])?->getKey();
            }

            foreach (['floor', 'price', 'facilities', 'notes'] as $field) {
                // Harga tidak boleh dikosongkan; kolom lain boleh null.
                if (array_key_exists($field, $data) && ($field !== 'price' || $data[$field] !== null)) {
                    $update[$field] = $data[$field];
                }
            }

            if (isset($status)) {
                $update['status'] = $status;
            }

            $room->update($update);

            return $room;
        });
    }
}
