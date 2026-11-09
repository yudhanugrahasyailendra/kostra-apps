<?php

namespace App\Actions;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\Tenant;

/** Menyamakan tenants.plan_id dan status dengan langganan berjalan. Tenant yang ditangguhkan atau ditutup tidak disentuh statusnya. */
class ApplySubscriptionToTenant
{
    public function handle(Tenant $tenant, Subscription $subscription): void
    {
        $tenant->plan_id = $subscription->plan_id;

        if ($subscription->trial_ends_at !== null) {
            $tenant->trial_ends_at = $subscription->trial_ends_at;
        }

        if (! $tenant->isReadOnly()) {
            $tenant->status = $subscription->status === SubscriptionStatus::Trialing ? TenantStatus::Trial : TenantStatus::Active;
        }

        $tenant->save();
        $tenant->load('plan');
    }
}
