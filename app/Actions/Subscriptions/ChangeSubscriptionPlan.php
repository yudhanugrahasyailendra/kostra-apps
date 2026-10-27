<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Inti semua perpindahan paket. Menutup langganan berjalan, membuat langganan baru, dan menyamakan
 * tenants.plan_id dengan langganan itu (salinan agar pengecekan batas cepat). Dijalankan dalam konteks
 * tenant yang bersangkutan, jadi aman dipanggil dari panel Super Admin maupun penjadwal.
 */
class ChangeSubscriptionPlan
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PlanLimits $limits,
    ) {}

    /**
     * @param  array{status?: SubscriptionStatus, period_start?: CarbonInterface, period_end?: ?CarbonInterface, trial_ends_at?: ?CarbonInterface, grace_ends_at?: ?CarbonInterface, close_as?: SubscriptionStatus}  $options
     * @param  bool  $checkLimits  true = tolak bila pemakaian sekarang melebihi batas paket tujuan (turun paket oleh pemilik)
     */
    public function __invoke(Tenant $tenant, Plan $plan, BillingPeriod $period, array $options = [], bool $checkLimits = true): Subscription
    {
        return $this->context->run($tenant, function () use ($tenant, $plan, $period, $options, $checkLimits) {
            return DB::transaction(function () use ($tenant, $plan, $period, $options, $checkLimits) {
                // Titik serialisasi perubahan paket per tenant.
                $tenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
                $tenant->setRelation('plan', Plan::query()->find($tenant->plan_id));

                if ($checkLimits) {
                    $this->limits->assertFits($tenant, $plan);
                }

                $status = $options['status'] ?? SubscriptionStatus::Active;
                $closeAs = $options['close_as'] ?? SubscriptionStatus::Canceled;

                Subscription::query()->current()->get()->each(fn (Subscription $old) => $old->update([
                    'status' => $closeAs,
                    'canceled_at' => $closeAs === SubscriptionStatus::Canceled ? now() : $old->canceled_at,
                ]));

                $today = $tenant->today();

                $subscription = Subscription::create([
                    'plan_id' => $plan->getKey(),
                    'status' => $status,
                    'billing_period' => $period,
                    'trial_ends_at' => $options['trial_ends_at'] ?? null,
                    'current_period_start' => ($options['period_start'] ?? $today)->toDateString(),
                    'current_period_end' => array_key_exists('period_end', $options) ? $options['period_end']?->toDateString() : null,
                    'grace_ends_at' => $options['grace_ends_at'] ?? null,
                ]);

                $attributes = [
                    'plan_id' => $plan->getKey(),
                    'trial_ends_at' => $status === SubscriptionStatus::Trialing ? ($options['trial_ends_at'] ?? null) : null,
                ];

                // Tenant yang ditangguhkan atau ditutup tidak otomatis aktif karena pindah paket; hanya pembayaran
                // (RecordSubscriptionPayment) atau Super Admin (ActivateTenant) yang membukanya.
                if (! in_array($tenant->status, [TenantStatus::Suspended, TenantStatus::Closed], true)) {
                    $attributes['status'] = $status === SubscriptionStatus::Trialing ? TenantStatus::Trial : TenantStatus::Active;
                }

                $tenant->update($attributes);
                $tenant->setRelation('plan', $plan);

                return $subscription->setRelation('plan', $plan);
            });
        });
    }
}
