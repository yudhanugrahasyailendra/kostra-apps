<?php

namespace App\Services\Plans;

use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
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

    /**
     * Pengguna yang memakai slot: pemilik dan staf (tidak terhapus) ditambah undangan yang masih berlaku.
     */
    public function usedUsers(Tenant $tenant): int
    {
        $this->assertContext($tenant);

        return User::query()->count() + Invitation::query()->pending()->count();
    }

    /**
     * Sisa slot pengguna (pemilik + staf); null = tanpa batas.
     */
    public function remainingUsers(Tenant $tenant): ?int
    {
        $max = $tenant->plan->max_users;

        return $max === null ? null : max(0, $max - $this->usedUsers($tenant));
    }

    public function ensureCanAddStaff(Tenant $tenant, int $count = 1): void
    {
        $remaining = $this->remainingUsers($tenant);

        if ($remaining !== null && $count > $remaining) {
            throw ValidationException::withMessages([
                'plan_limit' => "Paket {$tenant->plan->name} dibatasi {$tenant->plan->max_users} pengguna (pemilik dan staf). Naik paket untuk menambah staf.",
            ]);
        }
    }

    /**
     * Pemakaian saat ini, untuk halaman langganan dan panel admin.
     *
     * @return array{properties: int, rooms: int, users: int}
     */
    public function usage(Tenant $tenant): array
    {
        $this->assertContext($tenant);

        return [
            'properties' => Property::query()->count(),
            'rooms' => Room::query()->count(),
            'users' => $this->usedUsers($tenant),
        ];
    }

    /**
     * Alasan data yang ada tidak muat di paket $plan (kosong = muat). Dipakai saat turun paket: data tidak
     * dihapus, jadi turun paket ditolak bila pemakaian sekarang melebihi batas paket tujuan.
     *
     * @return list<string>
     */
    public function violations(Tenant $tenant, Plan $plan): array
    {
        $usage = $this->usage($tenant);
        $problems = [];

        foreach ([
            'properties' => ['properti', $plan->max_properties],
            'rooms' => ['kamar', $plan->max_rooms],
            'users' => ['pengguna', $plan->max_users],
        ] as $key => [$label, $max]) {
            if ($max !== null && $usage[$key] > $max) {
                $problems[] = "Anda punya {$usage[$key]} {$label}, batas paket {$plan->name} adalah {$max}.";
            }
        }

        return $problems;
    }

    /**
     * @throws ValidationException bila pemakaian sekarang melebihi batas paket tujuan
     */
    public function assertFits(Tenant $tenant, Plan $plan): void
    {
        $problems = $this->violations($tenant, $plan);

        if ($problems !== []) {
            throw ValidationException::withMessages([
                'plan' => 'Paket '.$plan->name.' belum bisa dipilih. '.implode(' ', $problems).' Kurangi dulu sebelum pindah.',
            ]);
        }
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
