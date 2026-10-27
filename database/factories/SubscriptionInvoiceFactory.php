<?php

namespace Database\Factories;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Berikan tenant dan langganan yang konsisten. Nomor harus unik: pakai Action RequestSubscription untuk
 * data yang menyerupai produksi, dan factory ini hanya untuk tes yang butuh baris mentah.
 *
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'number' => 'SUB-T-'.fake()->unique()->numerify('########'),
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->subDay()->toDateString(),
            'amount' => 199_000,
            'status' => SubscriptionInvoiceStatus::Pending,
            'due_date' => now()->addDays(7)->toDateString(),
        ];
    }
}
