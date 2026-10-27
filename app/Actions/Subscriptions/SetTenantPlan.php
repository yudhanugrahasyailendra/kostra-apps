<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
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
     * Super Admin memilih melewatinya secara eksplisit. Pengajuan dan tagihan yang belum dibayar dibatalkan.
     */
    public function __invoke(Tenant $tenant, Plan $plan, BillingPeriod $period, bool $ignoreLimits = false): Subscription
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Paket ini tidak aktif.']);
        }

        if ($tenant->status === TenantStatus::Closed) {
            throw ValidationException::withMessages(['plan' => 'Tenant yang sudah ditutup tidak bisa diubah paketnya.']);
        }

        $free = $plan->price_monthly === 0;

        if (! $free && $period === BillingPeriod::Yearly && $plan->price_yearly === null) {
            throw ValidationException::withMessages(['plan' => "Paket {$plan->name} tidak punya penagihan tahunan."]);
        }

        $today = $tenant->today();

        $subscription = ($this->change)($tenant, $plan, $period, [
            'status' => SubscriptionStatus::Active,
            'period_start' => $today,
            'period_end' => $free ? null : $today->addMonthsNoOverflow($period->months())->subDay(),
        ], checkLimits: ! $ignoreLimits);

        // Satu per satu (bukan update massal) supaya tiap pembatalan tercatat di audit.
        $this->context->run($tenant, function () use ($subscription) {
            SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::Pending->value)
                ->get()->each->update(['status' => SubscriptionInvoiceStatus::Void]);

            Subscription::query()->pending()->where('id', '!=', $subscription->getKey())
                ->get()->each->update(['status' => SubscriptionStatus::Expired]);
        });

        return $subscription;
    }
}
