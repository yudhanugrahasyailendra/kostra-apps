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

class RequestSubscription
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ChangeSubscriptionPlan $change,
        private readonly CreateSubscriptionInvoice $invoices,
    ) {}

    /**
     * Pemilik berlangganan paket berbayar (atau naik paket). Paket langsung terbuka dengan status "menunggu
     * pembayaran" dan tagihan jatuh tempo dalam masa tenggang (7 hari). Setelah Super Admin mencatat transfer,
     * langganan menjadi aktif; bila tidak dibayar sampai masa tenggang habis, tenant kembali ke paket Gratis
     * (RunSubscriptionLifecycle). Naik paket di tengah periode menagih satu periode penuh, tanpa prorata.
     */
    public function __invoke(Tenant $tenant, Plan $plan, BillingPeriod $period): SubscriptionInvoice
    {
        if ($plan->price_monthly <= 0 || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Pilih paket berbayar yang tersedia.']);
        }

        if ($period === BillingPeriod::Yearly && $plan->price_yearly === null) {
            throw ValidationException::withMessages(['plan' => "Paket {$plan->name} tidak punya penagihan tahunan."]);
        }

        return $this->context->run($tenant, function () use ($tenant, $plan, $period) {
            $current = $tenant->plan;

            if (SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::Pending->value)->exists()) {
                throw ValidationException::withMessages(['plan' => 'Masih ada tagihan langganan yang menunggu pembayaran. Selesaikan atau minta Super Admin membatalkannya dulu.']);
            }

            $subscription = Subscription::query()->current()->latest('id')->first();

            if ($current->getKey() === $plan->getKey() && $subscription?->status === SubscriptionStatus::Active) {
                throw ValidationException::withMessages(['plan' => "Anda sudah berlangganan paket {$plan->name}."]);
            }

            if ($current->price_monthly > $plan->price_monthly && $subscription?->status === SubscriptionStatus::Active) {
                throw ValidationException::withMessages(['plan' => 'Untuk pindah ke paket yang lebih murah, gunakan turun paket.']);
            }

            $today = $tenant->today();
            $graceDays = (int) config('kostra.grace_days');

            $new = ($this->change)($tenant, $plan, $period, [
                'status' => SubscriptionStatus::PastDue,
                'period_start' => $today,
                'period_end' => $today->addMonthsNoOverflow($period->months())->subDay(),
                'grace_ends_at' => $tenant->today()->addDays($graceDays + 1)->utc(),
                'close_as' => $subscription?->status === SubscriptionStatus::Trialing ? SubscriptionStatus::Expired : SubscriptionStatus::Canceled,
            ]);

            return ($this->invoices)($new, $today, $today->addDays($graceDays));
        });
    }
}
