<?php

namespace App\Actions\Rooms;

use App\Actions\Concerns\ResolvesTenant;
use App\Actions\Rooms\Concerns\ChecksRoomRules;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRoomsInBulk
{
    use ChecksRoomRules, ResolvesTenant;

    public const MAX_PER_BATCH = 100;

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * Membuat kamar bernomor berurutan (mis. 101 sampai 110). Seluruh batch batal bila
     * ada nomor bentrok atau batas paket terlewati.
     *
     * @param  array<string, mixed>  $data  property_id, from, to, [prefix, room_type_id, floor, price, facilities]
     * @return Collection<int, Room>
     */
    public function __invoke(array $data): Collection
    {
        $tenant = $this->currentTenant();

        $from = (int) $data['from'];
        $to = (int) $data['to'];
        $prefix = (string) ($data['prefix'] ?? '');

        if ($to < $from || ($to - $from + 1) > self::MAX_PER_BATCH) {
            throw ValidationException::withMessages([
                'to' => 'Rentang nomor tidak valid (maksimal '.self::MAX_PER_BATCH.' kamar per langkah).',
            ]);
        }

        $numbers = array_map(fn (int $n) => $prefix.$n, range($from, $to));

        if (max(array_map('mb_strlen', $numbers)) > 20) {
            throw ValidationException::withMessages(['prefix' => 'Nomor kamar maksimal 20 karakter.']);
        }

        return DB::transaction(function () use ($tenant, $data, $numbers) {
            $this->limits->lockTenant($tenant);
            $this->limits->ensureCanAddRooms($tenant, count($numbers));

            $property = Property::query()->findOrFail($data['property_id']);
            $type = $this->resolveRoomType($property, $data['room_type_id'] ?? null);

            $this->ensureNumbersAvailable($property, $numbers, null, 'from');

            $price = $data['price'] ?? $type?->base_price;

            if ($price === null) {
                throw ValidationException::withMessages(['price' => 'Harga wajib diisi bila tipe kamar tidak dipilih.']);
            }

            return collect($numbers)->map(fn (string $number) => Room::create([
                'property_id' => $property->getKey(),
                'room_type_id' => $type?->getKey(),
                'number' => $number,
                'floor' => $data['floor'] ?? null,
                'price' => $price,
                'status' => RoomStatus::Vacant,
                'facilities' => $data['facilities'] ?? null,
            ]));
        });
    }
}
