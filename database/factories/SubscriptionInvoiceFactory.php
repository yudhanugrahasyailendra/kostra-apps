<?php

namespace Database\Factories;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SubscriptionInvoice> */
class SubscriptionInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'number' => 'KST-TEST-'.Str::upper(Str::random(10)),
            'period_start' => today(),
            'period_end' => today()->addMonthNoOverflow()->subDay(),
            'amount' => 49000,
            'status' => SubscriptionInvoiceStatus::Pending,
            'due_date' => today()->addDays(7),
        ];
    }
}
