<?php

namespace App\Services\Plans;

use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Batas properti dan kamar sesuai paket tenant aktif (PRP-07, LGN-02).
 * Penghitungan langsung dari tabel (tanpa penghitung tersimpan) lewat model
 * yang sudah terscope tenant dan tidak menghitung baris yang dihapus lunak.
 */
class PlanLimits
{
    public function tenant(): Tenant
    {
        return TenantContext::instance()->tenant()
            ?? throw new PlanLimitExceededException('Tidak ada tenant aktif.');
    }

    public function maxProperties(): ?int
    {
        return $this->tenant()->plan->max_properties;
    }

    public function maxRooms(): ?int
    {
        return $this->tenant()->plan->max_rooms;
    }

    public function propertiesUsed(): int
    {
        return Property::query()->count();
    }

    public function roomsUsed(): int
    {
        return Room::query()->count();
    }

    /** Sisa kamar yang boleh ditambah; null = tanpa batas. */
    public function roomsRemaining(): ?int
    {
        $max = $this->maxRooms();

        return $max === null ? null : max(0, $max - $this->roomsUsed());
    }

    public function assertCanAddProperty(): void
    {
        $max = $this->maxProperties();

        if ($max !== null && $this->propertiesUsed() >= $max) {
            throw new PlanLimitExceededException(
                "Paket {$this->tenant()->plan->name} dibatasi {$max} properti. Naik paket untuk menambah properti."
            );
        }
    }

    public function assertCanAddRooms(int $count = 1): void
    {
        $remaining = $this->roomsRemaining();

        if ($remaining !== null && $count > $remaining) {
            $max = $this->maxRooms();

            throw new PlanLimitExceededException(
                "Paket {$this->tenant()->plan->name} dibatasi {$max} kamar (sisa {$remaining}). Naik paket untuk menambah kamar."
            );
        }
    }

    /**
     * Jalankan blok dalam transaksi dengan baris tenant terkunci, supaya dua
     * permintaan bersamaan tidak sama-sama lolos batas.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withinLock(Closure $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            Tenant::query()->whereKey($this->tenant()->getKey())->lockForUpdate()->first();

            return $callback();
        });
    }
}
