<?php

namespace App\Actions;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use Illuminate\Validation\ValidationException;

class CreateRoomsInBulk
{
    public const MAX_PER_BATCH = 100;

    public function __construct(private PlanLimits $limits) {}

    /**
     * Membuat kamar bernomor berurutan (mis. 101 sampai 120) dalam satu transaksi.
     * Semua atau tidak sama sekali: nomor yang sudah dipakai menggagalkan seluruh langkah.
     *
     * @param  array{property_id: int, start: int, end: int, prefix?: string|null, floor?: int|null, room_type_id?: int|null, price?: int|null}  $data
     * @return int jumlah kamar yang dibuat
     */
    public function handle(array $data): int
    {
        $prefix = (string) ($data['prefix'] ?? '');
        $numbers = [];

        for ($n = (int) $data['start']; $n <= (int) $data['end']; $n++) {
            $numbers[] = $prefix.$n;
        }

        if ($numbers === [] || count($numbers) > self::MAX_PER_BATCH) {
            throw ValidationException::withMessages(['end' => 'Rentang nomor harus 1 sampai '.self::MAX_PER_BATCH.' kamar.']);
        }

        if (collect($numbers)->contains(fn (string $number) => mb_strlen($number) > 20)) {
            throw ValidationException::withMessages(['prefix' => 'Nomor kamar tidak boleh lebih dari 20 karakter.']);
        }

        return $this->limits->withinLock(function () use ($data, $numbers) {
            $this->limits->assertCanAddRooms(count($numbers));

            $taken = Room::query()
                ->where('property_id', $data['property_id'])
                ->whereIn('number', $numbers)
                ->pluck('number')
                ->all();

            if ($taken !== []) {
                throw ValidationException::withMessages([
                    'start' => 'Nomor sudah dipakai di properti ini: '.implode(', ', array_slice($taken, 0, 10)).(count($taken) > 10 ? ', ...' : '').'.',
                ]);
            }

            $price = $data['price'] ?? null;

            if ($price === null && ! empty($data['room_type_id'])) {
                $price = RoomType::query()->findOrFail($data['room_type_id'])->base_price;
            }

            if ($price === null) {
                throw ValidationException::withMessages(['price' => 'Isi harga atau pilih tipe kamar.']);
            }

            foreach ($numbers as $number) {
                Room::create([
                    'property_id' => $data['property_id'],
                    'room_type_id' => $data['room_type_id'] ?? null,
                    'number' => $number,
                    'floor' => $data['floor'] ?? null,
                    'price' => $price,
                    'status' => RoomStatus::Vacant,
                ]);
            }

            return count($numbers);
        });
    }
}
