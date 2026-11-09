<?php

namespace App\Services\Plans;

use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
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

    public function maxUsers(): ?int
    {
        return $this->tenant()->plan->max_users;
    }

    /** Pengguna aktif ditambah undangan yang masih berlaku: keduanya memakai jatah yang sama. */
    public function usersUsed(): int
    {
        return User::query()->count()
            + Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->count();
    }

    public function hasFeature(string $feature): bool
    {
        return $this->tenant()->plan->hasFeature($feature);
    }

    /** Fitur di luar paket ditolak dengan tawaran naik paket, bukan galat teknis. */
    public function assertFeature(string $feature, string $label): void
    {
        if (! $this->hasFeature($feature)) {
            throw new PlanLimitExceededException(
                "Fitur {$label} tidak termasuk paket {$this->tenant()->plan->name}. Naik paket untuk memakainya."
            );
        }
    }

    public function assertCanAddUser(): void
    {
        $max = $this->maxUsers();

        if ($max !== null && $this->usersUsed() >= $max) {
            throw new PlanLimitExceededException(
                "Paket {$this->tenant()->plan->name} dibatasi {$max} pengguna. Naik paket untuk menambah staf."
            );
        }
    }

    /**
     * Turun paket hanya boleh bila pemakaian sekarang muat di paket tujuan (LGN-05).
     * Data yang ada tidak pernah dihapus; yang ditolak adalah turun paketnya.
     */
    public function assertFitsPlan(Plan $target): void
    {
        $checks = [
            ['properti', $this->propertiesUsed(), $target->max_properties],
            ['kamar', $this->roomsUsed(), $target->max_rooms],
            ['pengguna', User::query()->count(), $target->max_users],
        ];

        foreach ($checks as [$label, $used, $max]) {
            if ($max !== null && $used > $max) {
                throw new PlanLimitExceededException(
                    "Paket {$target->name} dibatasi {$max} {$label}, sedangkan Anda memakai {$used}. Kurangi dulu sebelum turun paket."
                );
            }
        }
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
