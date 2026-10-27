<?php

namespace App\Actions\Subscriptions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuspendTenant
{
    /**
     * Menangguhkan tenant: hanya bisa membaca dan mengekspor (EnsureTenantIsActive), tidak ada tagihan atau pesan
     * otomatis baru. Data tidak disentuh. Perubahan tercatat di audit lewat model Tenant.
     */
    public function __invoke(Tenant $tenant): Tenant
    {
        return DB::transaction(function () use ($tenant) {
            $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === TenantStatus::Closed) {
                throw ValidationException::withMessages(['general' => 'Tenant yang sudah ditutup tidak bisa ditangguhkan.']);
            }

            if ($locked->status !== TenantStatus::Suspended) {
                $locked->update(['status' => TenantStatus::Suspended, 'suspended_at' => now()]);
            }

            return $locked;
        });
    }
}
