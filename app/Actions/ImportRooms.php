<?php

namespace App\Actions;

use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use App\Support\CsvRows;
use Illuminate\Validation\ValidationException;

class ImportRooms
{
    public const MAX_ROWS = 500;

    /** Nama kolom yang dikenali (huruf kecil) dan padanannya. */
    private const HEADERS = [
        'nomor' => 'number', 'nomor kamar' => 'number', 'no' => 'number', 'number' => 'number',
        'lantai' => 'floor', 'floor' => 'floor',
        'tipe' => 'type', 'tipe kamar' => 'type', 'type' => 'type',
        'harga' => 'price', 'price' => 'price',
        'catatan' => 'notes', 'notes' => 'notes',
    ];

    public function __construct(private PlanLimits $limits) {}

    /**
     * Impor kamar dari CSV ke satu properti. Baris valid tetap masuk; baris gagal
     * dilaporkan beserta alasannya (PRP-05). Pemisah koma atau titik koma.
     *
     * @return array{created: int, failures: list<array{row: int, number: string, reason: string}>}
     */
    public function handle(Property $property, string $path): array
    {
        $rows = CsvRows::read($path, self::HEADERS, ['number' => 'nomor']);

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per impor.']);
        }

        return $this->limits->withinLock(function () use ($property, $rows) {
            $types = RoomType::query()
                ->where(fn ($q) => $q->whereNull('property_id')->orWhere('property_id', $property->id))
                ->get()
                ->keyBy(fn (RoomType $t) => mb_strtolower($t->name));

            $existing = Room::query()->where('property_id', $property->id)->pluck('number')->map(fn ($n) => mb_strtolower($n))->flip();
            $remaining = $this->limits->roomsRemaining();

            $created = 0;
            $failures = [];

            foreach ($rows as $line => $row) {
                $number = trim((string) ($row['number'] ?? ''));
                // Closure biasa dengan referensi: arrow function menyalin $failures sehingga tambahan hilang.
                $fail = function (string $reason) use (&$failures, $line, $number): void {
                    $failures[] = ['row' => $line, 'number' => $number, 'reason' => $reason];
                };

                if ($number === '' || mb_strlen($number) > 20) {
                    $fail('Nomor kamar kosong atau lebih dari 20 karakter.');

                    continue;
                }

                if ($existing->has(mb_strtolower($number))) {
                    $fail('Nomor kamar sudah dipakai di properti ini.');

                    continue;
                }

                $floorRaw = trim((string) ($row['floor'] ?? ''));

                if ($floorRaw !== '' && (! ctype_digit($floorRaw) || (int) $floorRaw > 255)) {
                    $fail('Lantai harus angka 0 sampai 255.');

                    continue;
                }

                $type = null;
                $typeName = trim((string) ($row['type'] ?? ''));

                if ($typeName !== '') {
                    $type = $types->get(mb_strtolower($typeName));

                    if ($type === null) {
                        $fail("Tipe kamar \"{$typeName}\" tidak ditemukan.");

                        continue;
                    }
                }

                $priceDigits = preg_replace('/\D+/', '', (string) ($row['price'] ?? ''));
                $price = $priceDigits !== '' ? (int) $priceDigits : $type?->base_price;

                if ($price === null) {
                    $fail('Harga kosong dan tipe kamar tidak diisi.');

                    continue;
                }

                if ($price > 4294967295) {
                    $fail('Harga terlalu besar.');

                    continue;
                }

                if ($remaining !== null && $remaining <= 0) {
                    $fail('Melebihi batas kamar paket Anda.');

                    continue;
                }

                Room::create([
                    'property_id' => $property->id,
                    'room_type_id' => $type?->id,
                    'number' => $number,
                    'floor' => $floorRaw === '' ? null : (int) $floorRaw,
                    'price' => $price,
                    'status' => RoomStatus::Vacant,
                    'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                ]);

                $existing->put(mb_strtolower($number), true);
                $created++;

                if ($remaining !== null) {
                    $remaining--;
                }
            }

            return ['created' => $created, 'failures' => $failures];
        });
    }
}
