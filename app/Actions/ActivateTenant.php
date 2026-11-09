<?php

namespace App\Actions;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateTenant
{
    public function __construct(private StartFreeSubscription $startFree) {}

    /**
     * Cabut penangguhan (LGN-07). Bila langganannya sudah berakhir karena tunggakan, tenant kembali ke paket
     * Gratis; pembayaran tagihan yang tertunggak (RecordSubscriptionPayment) mengembalikan paket berbayarnya.
     */
    public function handle(Tenant $tenant): Tenant
    {
        if ($tenant->status === TenantStatus::Closed) {
            throw ValidationException::withMessages(['tenant' => 'Tenant ini sudah ditutup dan tidak bisa diaktifkan kembali.']);
        }

        return DB::transaction(function () use ($tenant) {
            $tenant->forceFill([
                'status' => $tenant->currentSubscription()?->status === SubscriptionStatus::Trialing ? TenantStatus::Trial : TenantStatus::Active,
                'suspended_at' => null,
            ])->save();

            if ($tenant->currentSubscription() === null) {
                $this->startFree->handle($tenant);
            }

            return TenantContext::instance()->runAs($tenant, fn () => $tenant->refresh());
        });
    }
}
