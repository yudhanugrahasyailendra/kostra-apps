<?php

namespace Database\Factories;

use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'billing_period' => SubscriptionPeriod::Monthly,
            'current_period_start' => today(),
            'current_period_end' => today()->addMonthNoOverflow()->subDay(),
        ];
    }
}
