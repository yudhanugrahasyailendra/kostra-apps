<?php

namespace App\Actions\Rooms\Concerns;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Validation\ValidationException;

trait ChecksRoomRules
{
    /**
     * Nomor dari daftar yang sudah dipakai kamar lain di properti yang sama
     * (kamar terhapus lunak tidak dihitung, sehingga nomornya boleh dipakai ulang).
     *
     * @param  list<string>  $numbers
     * @return list<string>
     */
    protected function takenNumbers(Property $property, array $numbers, ?int $ignoreRoomId = null): array
    {
        return Room::query()
            ->where('property_id', $property->getKey())
            ->whereIn('number', $numbers)
            ->when($ignoreRoomId, fn ($query) => $query->whereKeyNot($ignoreRoomId))
            ->pluck('number')
            ->map(fn ($number) => (string) $number)
            ->all();
    }

    /**
     * @param  list<string>  $numbers
     */
    protected function ensureNumbersAvailable(Property $property, array $numbers, ?int $ignoreRoomId = null, string $field = 'number'): void
    {
        $taken = $this->takenNumbers($property, $numbers, $ignoreRoomId);

        if ($taken !== []) {
            $list = implode(', ', array_slice($taken, 0, 10));

            throw ValidationException::withMessages([
                $field => count($taken) === 1
                    ? "Nomor kamar {$list} sudah dipakai di properti ini."
                    : "Nomor kamar berikut sudah dipakai di properti ini: {$list}.",
            ]);
        }
    }

    /**
     * Tipe harus ada, aktif, dan berlaku untuk properti ini (tipe umum atau tipe milik properti ini).
     */
    protected function resolveRoomType(Property $property, int|string|null $roomTypeId, string $field = 'room_type_id'): ?RoomType
    {
        if ($roomTypeId === null || $roomTypeId === '') {
            return null;
        }

        $type = RoomType::query()->find($roomTypeId);

        if ($type === null
            || ! $type->is_active
            || ($type->property_id !== null && (int) $type->property_id !== (int) $property->getKey())) {
            throw ValidationException::withMessages([$field => 'Tipe kamar tidak tersedia untuk properti ini.']);
        }

        return $type;
    }
}
