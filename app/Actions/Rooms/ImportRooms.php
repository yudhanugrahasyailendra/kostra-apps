<?php

namespace App\Actions\Rooms;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportRooms
{
    use ResolvesTenant;

    public const MAX_ROWS = 1000;

    public const MAX_PRICE = 100_000_000;

    /** Alias nama kolom (huruf kecil) ke kolom baku. */
    private const HEADERS = [
        'nomor' => 'number', 'number' => 'number', 'no' => 'number',
        'lantai' => 'floor', 'floor' => 'floor',
        'tipe' => 'type', 'type' => 'type',
        'harga' => 'price', 'price' => 'price',
        'fasilitas' => 'facilities', 'facilities' => 'facilities',
    ];

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * Impor kamar dari CSV. Baris valid tetap masuk; baris gagal dilaporkan beserta alasannya.
     */
    public function __invoke(Property $property, string $path): ImportResult
    {
        $tenant = $this->currentTenant();
        $rows = $this->readRows($path);
        $result = new ImportResult;

        DB::transaction(function () use ($tenant, $property, $rows, $result) {
            $this->limits->lockTenant($tenant);

            $remaining = $this->limits->remainingRooms($tenant);
            $types = RoomType::query()->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('property_id')->orWhere('property_id', $property->getKey()))
                ->get()
                ->keyBy(fn (RoomType $type) => mb_strtolower($type->name));

            $existing = Room::query()->where('property_id', $property->getKey())
                ->pluck('number')->map(fn ($n) => mb_strtolower((string) $n))->flip()->all();

            foreach ($rows as ['line' => $line, 'data' => $row]) {
                $number = trim($row['number'] ?? '');
                $key = mb_strtolower($number);

                $error = $this->validateRow($row, $number, $types, $existing[$key] ?? null);

                if ($error === null && $remaining !== null && $remaining <= 0) {
                    $error = 'Melebihi batas kamar paket.';
                }

                if ($error !== null) {
                    $result->fail($line, $number, $error);

                    continue;
                }

                $type = ($row['type'] ?? '') !== '' ? $types[mb_strtolower(trim($row['type']))] : null;

                Room::create([
                    'property_id' => $property->getKey(),
                    'room_type_id' => $type?->getKey(),
                    'number' => $number,
                    'floor' => ($row['floor'] ?? '') !== '' ? (int) $row['floor'] : null,
                    'price' => ($row['price'] ?? '') !== '' ? Rupiah::parse($row['price']) : $type->base_price,
                    'status' => RoomStatus::Vacant,
                    'facilities' => $this->facilities($row['facilities'] ?? ''),
                ]);

                $existing[$key] = true;
                $result->created++;

                if ($remaining !== null) {
                    $remaining--;
                }
            }
        });

        return $result;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, RoomType>  $types
     */
    private function validateRow(array $row, string $number, $types, mixed $duplicate): ?string
    {
        if ($number === '') {
            return 'Nomor kamar kosong.';
        }

        if (mb_strlen($number) > 20) {
            return 'Nomor kamar lebih dari 20 karakter.';
        }

        if ($duplicate !== null) {
            return 'Nomor kamar sudah dipakai di properti ini (atau dobel di berkas).';
        }

        $floor = trim($row['floor'] ?? '');

        if ($floor !== '' && (! ctype_digit($floor) || (int) $floor > 99)) {
            return 'Lantai harus angka 0 sampai 99.';
        }

        $typeName = trim($row['type'] ?? '');
        $type = null;

        if ($typeName !== '') {
            $type = $types[mb_strtolower($typeName)] ?? null;

            if ($type === null) {
                return "Tipe kamar \"{$typeName}\" tidak ditemukan atau tidak aktif.";
            }
        }

        $price = trim($row['price'] ?? '');

        if ($price !== '') {
            $parsed = Rupiah::parse($price);

            if ($parsed === null || $parsed < 1 || $parsed > self::MAX_PRICE) {
                return 'Harga harus angka rupiah bulat antara 1 dan 100.000.000.';
            }
        } elseif ($type === null) {
            return 'Harga wajib diisi bila tipe kamar kosong.';
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function facilities(string $raw): ?array
    {
        $items = array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($item) => $item !== ''));

        return $items === [] ? null : array_slice(array_map(fn ($i) => mb_substr($i, 0, 60), $items), 0, 20);
    }

    /**
     * @return list<array{line: int, data: array<string, string>}>
     */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak bisa dibaca.']);
        }

        try {
            $first = fgets($handle);

            if ($first === false || trim($first) === '') {
                throw ValidationException::withMessages(['file' => 'Berkas kosong.']);
            }

            $first = $this->toUtf8(preg_replace('/^\xEF\xBB\xBF/', '', $first));
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

            $header = array_map(
                fn ($name) => self::HEADERS[mb_strtolower(trim((string) $name))] ?? null,
                str_getcsv($first, $delimiter, '"', ''),
            );

            if (! in_array('number', $header, true)) {
                throw ValidationException::withMessages(['file' => 'Kolom "nomor" tidak ditemukan di baris pertama.']);
            }

            $rows = [];
            $line = 1;

            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;

                if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per impor.']);
                }

                $data = [];

                foreach ($header as $index => $column) {
                    if ($column !== null) {
                        $data[$column] = $this->toUtf8((string) ($cells[$index] ?? ''));
                    }
                }

                $rows[] = ['line' => $line, 'data' => $data];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
