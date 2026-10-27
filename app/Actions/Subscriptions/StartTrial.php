<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartTrial
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ChangeSubscriptionPlan $change,
    ) {}

    /**
     * Masa percobaan 14 hari untuk paket berbayar, sekali seumur tenant. Paket langsung terbuka; setelah
     * masa percobaan tanpa pembayaran, penjadwal mengembalikan tenant ke paket Gratis (RunSubscriptionLifecycle).
     * Pemeriksaan dan pembuatan berjalan di bawah satu kunci tenant, jadi dua permintaan bersamaan tidak
     * bisa memulai dua percobaan.
     */
    public function __invoke(Tenant $tenant, Plan $plan): Subscription
    {
        if ($plan->price_monthly <= 0 || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Masa percobaan hanya untuk paket berbayar yang tersedia.']);
        }

        $days = (int) config('kostra.trial_days');

        return DB::transaction(function () use ($tenant, $plan, $days) {
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            $this->context->run($tenant, function () use ($tenant) {
                $currentPlan = Plan::query()->findOrFail(Tenant::query()->whereKey($tenant->getKey())->value('plan_id'));

                if ($currentPlan->price_monthly > 0) {
                    throw ValidationException::withMessages(['plan' => 'Masa percobaan hanya untuk tenant di paket Gratis.']);
                }

                if (Subscription::query()->whereNotNull('trial_ends_at')->exists()) {
                    throw ValidationException::withMessages(['plan' => 'Masa percobaan sudah pernah dipakai. Pilih berlangganan untuk melanjutkan.']);
                }
            });

            return ($this->change)($tenant, $plan, BillingPeriod::Monthly, [
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays($days),
                'period_start' => $tenant->today(),
                'period_end' => $tenant->today()->addDays($days),
                'close_as' => SubscriptionStatus::Expired,
            ]);
        });
    }
}
