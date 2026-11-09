<?php

namespace App\Actions;

use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;

class StartFreeSubscription
{
    public function __construct(private ApplySubscriptionToTenant $apply) {}

    /** Langganan paket Gratis untuk tenant baru, atau saat kembali ke Gratis. Periode hanya penanda: Gratis tidak ditagih. */
    public function handle(Tenant $tenant): Subscription
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant) {
            $plan = Plan::query()->where('key', 'free')->firstOrFail();
            $start = BillingClock::today($tenant);

            $subscription = new Subscription([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'billing_period' => SubscriptionPeriod::Monthly,
                'current_period_start' => $start->toDateString(),
                'current_period_end' => SubscriptionPeriod::Monthly->endFrom($start)->toDateString(),
            ]);
            $subscription->save();
            $subscription->setRelation('plan', $plan);

            $this->apply->handle($tenant, $subscription);

            return $subscription;
        });
    }
}
