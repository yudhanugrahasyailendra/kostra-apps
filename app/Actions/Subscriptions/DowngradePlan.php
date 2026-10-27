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

class DowngradePlan
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ChangeSubscriptionPlan $change,
    ) {}

    /**
     * Turun paket oleh pemilik. Ditolak bila properti, kamar, atau pengguna yang ada melebihi batas paket tujuan
     * (data tidak pernah dihapus untuk memuat paket). Turun ke paket berbayar yang lebih murah mempertahankan periode
     * berjalan; turun ke Gratis mengakhiri langganan berbayar. Tidak ada pengembalian dana (MVP).
     */
    public function __invoke(Tenant $tenant, Plan $plan): Subscription
    {
        return $this->context->run($tenant, function () use ($tenant, $plan) {
            if ($plan->price_monthly >= $tenant->plan->price_monthly) {
                throw ValidationException::withMessages(['plan' => 'Pilih paket yang lebih murah dari paket sekarang.']);
            }

            $current = Subscription::query()->current()->latest('id')->first();

            // Pemeriksaan batas dilakukan ChangeSubscriptionPlan (assertFits) sebelum apa pun berubah.
            $new = ($this->change)($tenant, $plan, $current?->billing_period ?? BillingPeriod::Monthly, $plan->price_monthly === 0
                ? ['status' => SubscriptionStatus::Active, 'period_end' => null]
                : ['status' => SubscriptionStatus::Active, 'period_start' => $current->current_period_start, 'period_end' => $current->current_period_end]);

            // Tagihan yang belum dibayar untuk paket lama tidak berlaku lagi.
            SubscriptionInvoice::query()
                ->where('status', SubscriptionInvoiceStatus::Pending->value)
                ->where('subscription_id', '!=', $new->getKey())
                ->update(['status' => SubscriptionInvoiceStatus::Void->value]);

            return $new;
        });
    }
}
