<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Berikan tenant dan paket yang konsisten: Subscription::factory()->for($tenant)->for($plan).
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'billing_period' => BillingPeriod::Monthly,
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
        ];
    }
}
