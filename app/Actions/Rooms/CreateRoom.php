<?php

namespace App\Actions\Rooms;

use App\Actions\Concerns\ResolvesTenant;
use App\Actions\Rooms\Concerns\ChecksRoomRules;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRoom
{
    use ChecksRoomRules, ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * @param  array<string, mixed>  $data  property_id, number, [room_type_id, floor, price, status, facilities, notes]
     */
    public function __invoke(array $data): Room
    {
        $tenant = $this->currentTenant();

        return DB::transaction(function () use ($tenant, $data) {
            $this->limits->lockTenant($tenant);
            $this->limits->ensureCanAddRooms($tenant);

            $property = Property::query()->findOrFail($data['property_id']);
            $type = $this->resolveRoomType($property, $data['room_type_id'] ?? null);
            $number = trim((string) $data['number']);

            $this->ensureNumbersAvailable($property, [$number]);

            $price = $data['price'] ?? $type?->base_price;

            if ($price === null) {
                throw ValidationException::withMessages(['price' => 'Harga wajib diisi bila tipe kamar tidak dipilih.']);
            }

            $status = isset($data['status']) ? RoomStatus::from($data['status']) : RoomStatus::Vacant;

            if ($status === RoomStatus::Occupied) {
                throw ValidationException::withMessages(['status' => 'Status terisi ditentukan oleh kontrak, bukan diatur manual.']);
            }

            return Room::create([
                'property_id' => $property->getKey(),
                'room_type_id' => $type?->getKey(),
                'number' => $number,
                'floor' => $data['floor'] ?? null,
                'price' => $price,
                'status' => $status,
                'facilities' => $data['facilities'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
