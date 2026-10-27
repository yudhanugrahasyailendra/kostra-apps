<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class SetTenantPlan
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ChangeSubscriptionPlan $change,
    ) {}

    /**
     * Super Admin menetapkan paket tenant langsung (mis. promosi, koreksi, atau kesepakatan di luar sistem), tanpa
     * tagihan. Langganan menjadi aktif untuk satu periode dari hari ini. Batas paket tujuan tetap diperiksa kecuali
     * Super Admin memilih melewatinya secara eksplisit.
     */
    public function __invoke(Tenant $tenant, Plan $plan, BillingPeriod $period, bool $ignoreLimits = false): Subscription
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Paket ini tidak aktif.']);
        }

        $free = $plan->price_monthly === 0;
        $today = $tenant->today();

        $subscription = ($this->change)($tenant, $plan, $period, [
            'status' => SubscriptionStatus::Active,
            'period_start' => $today,
            'period_end' => $free ? null : $today->addMonthsNoOverflow($period->months())->subDay(),
        ], checkLimits: ! $ignoreLimits);

        // Tagihan menunggu pembayaran untuk paket sebelumnya tidak berlaku lagi.
        $this->context->run($tenant, fn () => SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->where('subscription_id', '!=', $subscription->getKey())
            ->update(['status' => SubscriptionInvoiceStatus::Void->value]));

        return $subscription;
    }
}
