<?php

namespace App\Services\Reports;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Okupansi pada suatu tanggal, dari riwayat kontrak (bukan status kamar hari ini). Sebuah kamar terisi
 * pada tanggal D bila ada kontrak tidak batal dengan start_date <= D dan tanggal check-out kosong atau
 * sesudah D (kamar yang ditinggalkan pada D dihitung kosong, sama dengan status kamar saat check-out).
 * Pindah kamar memecah kontrak menjadi ruas per kamar menurut room_transfers.
 *
 * Penyebut adalah kamar yang ada sekarang (belum dihapus); riwayat pembuatan kamar tidak dilacak.
 * "Hari kosong" dihitung dari awal bulan tanggal itu sampai tanggal itu.
 */
class OccupancyReport
{
    /**
     * @return array{
     *   date: string, total: int, occupied: int, vacant: int, percent: float, avg_vacant_days: float, window_days: int,
     *   properties: list<array{id: int, name: string, total: int, occupied: int, vacant: int, percent: float, avg_vacant_days: float}>
     * }
     */
    public function at(CarbonImmutable $date, ?int $propertyId = null): array
    {
        $windowStart = $date->startOfMonth();
        $windowDays = (int) $windowStart->diffInDays($date) + 1;

        $rooms = Room::query()
            ->when($propertyId, fn (Builder $q) => $q->where('property_id', $propertyId))
            ->get(['id', 'property_id']);
        $roomIds = $rooms->pluck('id')->flip();

        $occupied = [];
        $coveredDays = [];

        foreach ($this->segments($windowStart, $date) as $segment) {
            if (! $roomIds->has($segment['room_id'])) {
                continue;
            }

            if ($segment['start']->lte($date) && ($segment['end'] === null || $segment['end']->gt($date))) {
                $occupied[$segment['room_id']] = true;
            }

            $from = $segment['start']->max($windowStart);
            $to = $segment['end'] === null ? $date : $segment['end']->subDay()->min($date);

            if ($to->gte($from)) {
                $coveredDays[$segment['room_id']] = ($coveredDays[$segment['room_id']] ?? 0) + (int) $from->diffInDays($to) + 1;
            }
        }

        $properties = Property::query()
            ->when($propertyId, fn (Builder $q) => $q->where('id', $propertyId))
            ->orderBy('name')->get(['id', 'name'])
            ->map(function (Property $property) use ($rooms, $occupied, $coveredDays, $windowDays) {
                return $this->figures(
                    $rooms->where('property_id', $property->id),
                    $occupied,
                    $coveredDays,
                    $windowDays,
                ) + ['id' => $property->id, 'name' => $property->name];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values()
            ->all();

        return $this->figures($rooms, $occupied, $coveredDays, $windowDays) + [
            'date' => $date->toDateString(),
            'window_days' => $windowDays,
            'properties' => $properties,
        ];
    }

    /**
     * @param  Collection<int, Room>  $rooms
     * @param  array<int, true>  $occupied
     * @param  array<int, int>  $coveredDays
     * @return array{total: int, occupied: int, vacant: int, percent: float, avg_vacant_days: float}
     */
    private function figures(Collection $rooms, array $occupied, array $coveredDays, int $windowDays): array
    {
        $total = $rooms->count();
        $occupiedCount = $rooms->filter(fn (Room $room) => isset($occupied[$room->id]))->count();
        $vacantDays = $rooms->sum(fn (Room $room) => max(0, $windowDays - ($coveredDays[$room->id] ?? 0)));

        return [
            'total' => $total,
            'occupied' => $occupiedCount,
            'vacant' => $total - $occupiedCount,
            'percent' => $total === 0 ? 0.0 : round($occupiedCount / $total * 100, 1),
            'avg_vacant_days' => $total === 0 ? 0.0 : round($vacantDays / $total, 1),
        ];
    }

    /**
     * Ruas hunian: (kamar, mulai, akhir eksklusif atau null) untuk kontrak yang beririsan dengan jendela.
     *
     * @return list<array{room_id: int, start: CarbonImmutable, end: CarbonImmutable|null}>
     */
    private function segments(CarbonImmutable $windowStart, CarbonImmutable $windowEnd): array
    {
        $tenantZone = TenantContext::instance()->tenant()?->timezone ?: config('kostra.display_timezone');

        $leases = Lease::query()
            ->where('status', '!=', LeaseStatus::Canceled->value)
            ->where('start_date', '<=', $windowEnd->toDateString())
            // Pra-saring kasar (selisih zona waktu sehari); ketepatan tanggal dihitung di bawah.
            ->where(fn (Builder $q) => $q->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $windowStart->subDay()->startOfDay()))
            ->with(['transfers' => fn ($q) => $q->orderBy('transferred_on')->orderBy('id')])
            ->get();

        $segments = [];

        foreach ($leases as $lease) {
            $end = $lease->checked_out_at === null
                ? null
                : CarbonImmutable::createFromFormat('!Y-m-d', $lease->checked_out_at->copy()->setTimezone($tenantZone)->toDateString(), 'UTC');

            if ($end !== null && $end->lte($windowStart)) {
                continue;
            }

            $start = CarbonImmutable::createFromFormat('!Y-m-d', $lease->start_date->toDateString(), 'UTC');
            $room = $lease->transfers->first()?->from_room_id ?? $lease->room_id;

            foreach ($lease->transfers as $transfer) {
                $movedOn = CarbonImmutable::createFromFormat('!Y-m-d', $transfer->transferred_on->toDateString(), 'UTC');
                $segments[] = ['room_id' => $room, 'start' => $start, 'end' => $movedOn];
                $room = $transfer->to_room_id;
                $start = $movedOn;
            }

            $segments[] = ['room_id' => $room, 'start' => $start, 'end' => $end];
        }

        return $segments;
    }
}
