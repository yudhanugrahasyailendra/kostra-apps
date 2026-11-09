<?php

namespace App\Services\Reports;

use App\Models\Lease;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Kontrak aktif yang berakhir dalam N hari (hari ini dan hari ke-N termasuk), menurut tanggal tenant. */
class ExpiringLeasesReport
{
    public const WITHIN_DAYS = 30;

    /** @return Builder<Lease> */
    private function query(CarbonImmutable $today, int $days): Builder
    {
        return Lease::query()->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays($days)->toDateString()]);
    }

    public function count(CarbonImmutable $today, int $days = self::WITHIN_DAYS): int
    {
        return $this->query($today, $days)->count();
    }

    /** @return Collection<int, array<string, scalar|null>> */
    public function rows(CarbonImmutable $today, int $days = self::WITHIN_DAYS, int $limit = 5): Collection
    {
        return $this->query($today, $days)
            ->with(['room', 'primaryResident'])
            ->orderBy('end_date')->orderBy('id')
            ->limit($limit)->get()
            ->map(fn (Lease $lease) => [
                'lease_id' => $lease->id,
                'resident_name' => $lease->primaryResident->name,
                'room_number' => $lease->room->number,
                'end_date' => $lease->end_date->toDateString(),
                'days_left' => (int) $today->diffInDays($lease->end_date),
            ]);
    }
}
