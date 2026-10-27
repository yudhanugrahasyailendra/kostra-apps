<?php

namespace App\Services\Reports;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Okupansi pada satu tanggal, dihitung dari riwayat kontrak (bukan dari rooms.status yang hanya
 * mencerminkan hari ini). Satu kamar terisi pada tanggal D bila ada kontrak (tidak dibatalkan) dengan
 * start_date <= D dan belum check-out pada akhir hari D; kamar yang dipindahkan setelah D dihitung di
 * kamar asalnya lewat room_transfers. Kamar dihitung sejak dibuat sampai dihapus.
 */
class OccupancyReport
{
    /**
     * @return array{date: string, total: int, occupied: int, vacant: int, percent: float, avg_vacant_days: float, by_property: list<array{property_id: int, name: string, total: int, occupied: int, vacant: int, percent: float}>}
     */
    public function onDate(Tenant $tenant, CarbonImmutable $date, ?int $propertyId = null): array
    {
        $timezone = $tenant->timezone ?: config('app.timezone');
        $day = $date->toDateString();
        // Akhir hari D menurut zona waktu tenant, dalam UTC (cara timestamp disimpan).
        $cut = CarbonImmutable::createFromFormat('!Y-m-d', $day, $timezone)->addDay()->utc();

        $rooms = Room::query()->withTrashed()
            ->where('created_at', '<', $cut)
            ->where(fn (Builder $query) => $query->whereNull('deleted_at')->orWhere('deleted_at', '>=', $cut))
            ->when($propertyId, fn (Builder $query) => $query->where('property_id', $propertyId))
            ->get(['id', 'property_id', 'created_at']);

        $occupiedIds = $this->occupiedRoomIds($day, $cut);

        $occupiedRooms = $rooms->filter(fn (Room $room) => isset($occupiedIds[$room->id]));
        $vacantRooms = $rooms->reject(fn (Room $room) => isset($occupiedIds[$room->id]));

        $names = Property::query()->withTrashed()->whereIn('id', $rooms->pluck('property_id')->unique())->pluck('name', 'id');

        $byProperty = $rooms->groupBy('property_id')->map(function ($group, $id) use ($occupiedIds, $names) {
            $occupied = $group->filter(fn (Room $room) => isset($occupiedIds[$room->id]))->count();

            return [
                'property_id' => (int) $id,
                'name' => $names[$id] ?? 'Properti dihapus',
                'total' => $group->count(),
                'occupied' => $occupied,
                'vacant' => $group->count() - $occupied,
                'percent' => self::percent($occupied, $group->count()),
            ];
        })->sortBy('name')->values()->all();

        return [
            'date' => $day,
            'total' => $rooms->count(),
            'occupied' => $occupiedRooms->count(),
            'vacant' => $vacantRooms->count(),
            'percent' => self::percent($occupiedRooms->count(), $rooms->count()),
            'avg_vacant_days' => $this->averageVacantDays($vacantRooms, $day, $cut, $timezone),
            'by_property' => $byProperty,
        ];
    }

    public function table(Tenant $tenant, CarbonImmutable $date, ?int $propertyId, array $meta): ReportTable
    {
        $snapshot = $this->onDate($tenant, $date, $propertyId);

        $rows = array_map(fn (array $row) => [
            'property' => $row['name'],
            'total' => $row['total'],
            'occupied' => $row['occupied'],
            'vacant' => $row['vacant'],
            'percent' => $row['percent'],
        ], $snapshot['by_property']);

        return new ReportTable(
            key: 'okupansi',
            title: 'Laporan okupansi',
            meta: $meta,
            columns: [
                ['key' => 'property', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'total', 'label' => 'Jumlah kamar', 'type' => 'int'],
                ['key' => 'occupied', 'label' => 'Terisi', 'type' => 'int'],
                ['key' => 'vacant', 'label' => 'Kosong', 'type' => 'int'],
                ['key' => 'percent', 'label' => 'Okupansi', 'type' => 'percent'],
            ],
            rows: $rows,
            totals: [
                'property' => 'Total',
                'total' => $snapshot['total'],
                'occupied' => $snapshot['occupied'],
                'vacant' => $snapshot['vacant'],
                'percent' => $snapshot['percent'],
            ],
            summary: [
                ['label' => 'Rata-rata hari kosong per kamar kosong', 'value' => $snapshot['avg_vacant_days'], 'type' => 'decimal'],
            ],
        );
    }

    /**
     * @return array<int, true> id kamar yang terisi pada tanggal itu
     */
    private function occupiedRoomIds(string $day, CarbonImmutable $cut): array
    {
        $leases = Lease::query()
            ->where('status', '!=', LeaseStatus::Canceled->value)
            ->where('start_date', '<=', $day)
            ->where(fn (Builder $query) => $query->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $cut))
            ->get(['id', 'room_id']);

        if ($leases->isEmpty()) {
            return [];
        }

        // Pindah kamar setelah D: pada tanggal D kontrak masih berada di kamar asal transfer pertama setelahnya.
        $originAfter = RoomTransfer::query()
            ->whereIn('lease_id', $leases->pluck('id'))
            ->where('transferred_on', '>', $day)
            ->orderBy('transferred_on')->orderBy('id')
            ->get(['lease_id', 'from_room_id'])
            ->unique('lease_id')
            ->pluck('from_room_id', 'lease_id');

        $roomIds = [];

        foreach ($leases as $lease) {
            $roomIds[(int) ($originAfter[$lease->id] ?? $lease->room_id)] = true;
        }

        // Filter properti terjadi saat dipotong dengan daftar kamar di onDate().
        return $roomIds;
    }

    /**
     * Rata-rata hari kosong: sejak kontrak terakhir selesai (atau kamar dipindahkan keluar), atau sejak
     * kamar dibuat bila belum pernah terisi, sampai tanggal D.
     *
     * @param  Collection<int, Room>  $vacantRooms
     */
    private function averageVacantDays($vacantRooms, string $day, CarbonImmutable $cut, string $timezone): float
    {
        if ($vacantRooms->isEmpty()) {
            return 0.0;
        }

        $ids = $vacantRooms->pluck('id');

        $lastCheckout = Lease::query()
            ->whereIn('room_id', $ids)
            ->whereNotNull('checked_out_at')->where('checked_out_at', '<', $cut)
            ->groupBy('room_id')
            ->selectRaw('room_id, MAX(checked_out_at) as last_at')
            ->pluck('last_at', 'room_id')
            ->map(fn ($at) => CarbonImmutable::parse($at, 'UTC')->setTimezone($timezone)->toDateString());

        $lastTransferOut = RoomTransfer::query()
            ->whereIn('from_room_id', $ids)->where('transferred_on', '<=', $day)
            ->groupBy('from_room_id')
            ->selectRaw('from_room_id, MAX(transferred_on) as last_on')
            ->pluck('last_on', 'from_room_id')
            ->map(fn ($on) => substr((string) $on, 0, 10));

        $target = new \DateTimeImmutable($day);

        $days = $vacantRooms->map(function (Room $room) use ($lastCheckout, $lastTransferOut, $timezone, $target) {
            $since = collect([
                $lastCheckout[$room->id] ?? null,
                $lastTransferOut[$room->id] ?? null,
            ])->filter()->max() ?? CarbonImmutable::parse($room->created_at, 'UTC')->setTimezone($timezone)->toDateString();

            $diff = (new \DateTimeImmutable($since))->diff($target);

            return $diff->invert ? 0 : (int) $diff->days;
        });

        return round($days->avg(), 1);
    }

    private static function percent(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }
}
