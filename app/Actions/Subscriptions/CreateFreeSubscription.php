<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

class CreateFreeSubscription
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Langganan awal paket Gratis (saat pendaftaran) atau untuk tenant lama yang belum punya langganan.
     * Idempoten: tenant yang sudah punya langganan berjalan tidak diubah.
     */
    public function __invoke(Tenant $tenant, ?Plan $plan = null): Subscription
    {
        return $this->context->run($tenant, function () use ($tenant, $plan) {
            $existing = Subscription::query()->current()->latest('id')->first();

            if ($existing !== null) {
                return $existing;
            }

            $plan ??= $tenant->plan;

            return Subscription::create([
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Active,
                'billing_period' => BillingPeriod::Monthly,
                'current_period_start' => $tenant->today()->toDateString(),
                'current_period_end' => null,
            ]);
        });
    }
}
