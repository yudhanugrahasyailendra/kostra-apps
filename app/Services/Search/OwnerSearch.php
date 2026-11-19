<?php

namespace App\Services\Search;

use App\Models\Lease;
use App\Models\Resident;
use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * Pencarian cepat di kepala halaman pemilik: kamar (nomor) dan penghuni (nama, pekerjaan, email, nomor).
 * Semua query lewat model ber-TenantScope. Hasil hanya memuat kolom yang ditampilkan di daftar hasil;
 * nomor telepon dan email ikut dicocokkan tetapi tidak dikirim balik.
 */
class OwnerSearch
{
    public const LIMIT = 5;

    /**
     * @return array{rooms: list<array<string, mixed>>, residents: list<array<string, mixed>>}
     */
    public function run(string $q, bool $rooms = true, bool $residents = true): array
    {
        return [
            'rooms' => $rooms ? $this->rooms($q) : [],
            'residents' => $residents ? $this->residents($q) : [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rooms(string $q): array
    {
        $like = '%'.addcslashes($q, '\\%_').'%';

        $rooms = Room::query()
            ->with('property:id,name')
            ->where('number', 'like', $like)
            ->orderBy('number')->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'property_id', 'number', 'floor', 'status']);

        $leases = $this->activeLeases('active_room_id', $rooms->pluck('id'));

        return $rooms->map(function (Room $room) use ($leases) {
            $lease = $leases->get($room->id);

            return [
                'id' => $room->id,
                'number' => $room->number,
                'property_name' => $room->property?->name,
                'floor' => $room->floor,
                'status' => $room->status->value,
                'status_label' => $room->status->label(),
                'status_tone' => $room->status->tone(),
                'resident_name' => $lease?->primaryResident?->name,
                // Kamar belum punya halaman detail: kamar terisi menuju kontraknya, lainnya ke daftar kamar properti itu.
                'href' => $lease
                    ? route('owner.leases.show', $lease->id, absolute: false)
                    : route('owner.rooms.index', ['property_id' => $room->property_id], absolute: false),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function residents(string $q): array
    {
        $residents = Resident::query()
            ->search($q)
            ->orderByDesc('is_active')->orderBy('name')->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'is_active']);

        $leases = $this->activeLeases('primary_resident_id', $residents->pluck('id'));

        return $residents->map(fn (Resident $resident) => [
            'id' => $resident->id,
            'name' => $resident->name,
            'is_active' => $resident->is_active,
            'status_label' => $resident->is_active ? 'Aktif' : 'Tidak aktif',
            'room_number' => $leases->get($resident->id)?->room?->number,
            'href' => route('owner.residents.show', $resident->id, absolute: false),
        ])->values()->all();
    }

    /**
     * Kontrak aktif yang terkait hasil, dikunci per kolom $key (kamar atau penghuni utama).
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Lease>
     */
    private function activeLeases(string $key, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Lease::query()
            ->active()
            ->whereIn($key, $ids)
            ->with(['primaryResident:id,name', 'room:id,number'])
            ->get(['id', 'room_id', 'primary_resident_id', 'active_room_id'])
            ->keyBy($key);
    }
}
