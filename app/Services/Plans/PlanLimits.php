<?php

namespace App\Services\Plans;

use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Batas paket (properti dan kamar), diperiksa di server saat membuat data baru.
 * Penghitungan memakai query terscope tenant (baris terhapus lunak tidak dihitung),
 * jadi konteks tenant wajib sama dengan tenant yang diperiksa.
 */
class PlanLimits
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Titik serialisasi penulisan per tenant: panggil di dalam DB::transaction
     * sebelum memeriksa batas atau keunikan, supaya permintaan paralel tidak saling melewati.
     */
    public function lockTenant(Tenant $tenant): void
    {
        Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();
    }

    /**
     * Sisa slot properti; null = tanpa batas.
     */
    public function remainingProperties(Tenant $tenant): ?int
    {
        $this->assertContext($tenant);

        $max = $tenant->plan->max_properties;

        return $max === null ? null : max(0, $max - Property::query()->count());
    }

    /**
     * Sisa slot kamar; null = tanpa batas.
     */
    public function remainingRooms(Tenant $tenant): ?int
    {
        $this->assertContext($tenant);

        $max = $tenant->plan->max_rooms;

        return $max === null ? null : max(0, $max - Room::query()->count());
    }

    public function ensureCanAddProperties(Tenant $tenant, int $count = 1): void
    {
        $remaining = $this->remainingProperties($tenant);

        if ($remaining !== null && $count > $remaining) {
            throw ValidationException::withMessages([
                'plan_limit' => "Paket {$tenant->plan->name} dibatasi {$tenant->plan->max_properties} properti. Naik paket untuk menambah properti.",
            ]);
        }
    }

    public function ensureCanAddRooms(Tenant $tenant, int $count = 1): void
    {
        $remaining = $this->remainingRooms($tenant);

        if ($remaining !== null && $count > $remaining) {
            throw ValidationException::withMessages([
                'plan_limit' => "Paket {$tenant->plan->name} dibatasi {$tenant->plan->max_rooms} kamar (sisa {$remaining}). Naik paket untuk menambah kamar.",
            ]);
        }
    }

    private function assertContext(Tenant $tenant): void
    {
        if ($this->context->id() !== $tenant->getKey()) {
            throw new LogicException('PlanLimits harus dipanggil dalam konteks tenant yang diperiksa.');
        }
    }
}
