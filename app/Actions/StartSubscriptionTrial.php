<?php

namespace App\Actions;

use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartSubscriptionTrial
{
    public function __construct(
        private IssueSubscriptionInvoice $issue,
        private ApplySubscriptionToTenant $apply,
    ) {}

    /** Percobaan hanya satu kali per tenant (LGN-03). */
    public static function eligible(Tenant $tenant): bool
    {
        return TenantContext::instance()->runAs($tenant, fn () => ! Subscription::query()->whereNotNull('trial_ends_at')->exists());
    }

    /** Hanya dari paket Gratis. Tagihan pertama terbit sekarang untuk periode yang dimulai setelah percobaan. */
    public function handle(Tenant $tenant, Plan $plan, SubscriptionPeriod $period): Subscription
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $plan, $period) {
            return DB::transaction(function () use ($tenant, $plan, $period) {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();

                if ($plan->isFree() || ! $plan->is_active) {
                    throw ValidationException::withMessages(['plan' => 'Pilih paket berbayar yang tersedia untuk masa percobaan.']);
                }

                $current = $tenant->currentSubscription();

                if ($current !== null && ! $current->plan->isFree()) {
                    throw ValidationException::withMessages(['plan' => 'Masa percobaan hanya untuk tenant yang masih di paket Gratis.']);
                }

                if (! self::eligible($tenant)) {
                    throw ValidationException::withMessages(['plan' => 'Masa percobaan hanya berlaku satu kali. Pilih paket untuk berlangganan.']);
                }

                $current?->forceFill(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()])->save();

                $today = BillingClock::today($tenant);
                $trialDays = (int) config('kostra.subscription.trial_days');

                $subscription = new Subscription([
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Trialing,
                    'billing_period' => $period,
                    'trial_ends_at' => now()->addDays($trialDays),
                    'current_period_start' => $today->toDateString(),
                    'current_period_end' => $today->addDays($trialDays)->toDateString(),
                ]);
                $subscription->save();
                $subscription->setRelation('plan', $plan);

                // Periode berbayar pertama dimulai setelah percobaan berakhir.
                $this->issue->handle($subscription, $today->addDays($trialDays), $today->addDays($trialDays));

                $this->apply->handle($tenant, $subscription);

                return $subscription;
            });
        });
    }
}
