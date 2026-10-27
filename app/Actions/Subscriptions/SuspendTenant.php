<?php

namespace App\Actions\Subscriptions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuspendTenant
{
    /** Tunggakan langganan: dibuka kembali otomatis oleh pembayaran tagihan langganan. */
    public const REASON_ARREARS = 'arrears';

    /** Keputusan Super Admin (mis. pelanggaran): hanya Super Admin yang membukanya; pembayaran tidak. */
    public const REASON_MANUAL = 'manual';

    /**
     * Menangguhkan tenant: hanya bisa membaca dan mengekspor (EnsureTenantIsActive), tidak ada tagihan atau pesan
     * otomatis baru. Data tidak disentuh. Alasan disimpan di tenants.settings.suspension_reason; perubahan tercatat
     * di audit lewat model Tenant.
     */
    public function __invoke(Tenant $tenant, string $reason = self::REASON_MANUAL): Tenant
    {
        return DB::transaction(function () use ($tenant, $reason) {
            $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === TenantStatus::Closed) {
                throw ValidationException::withMessages(['general' => 'Tenant yang sudah ditutup tidak bisa ditangguhkan.']);
            }

            if ($locked->status !== TenantStatus::Suspended) {
                $locked->update([
                    'status' => TenantStatus::Suspended,
                    'suspended_at' => now(),
                    'settings' => ($locked->settings ?? []) + ['suspension_reason' => $reason],
                ]);
            }

            return $locked;
        });
    }
}
