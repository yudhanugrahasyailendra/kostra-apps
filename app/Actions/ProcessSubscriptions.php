<?php

namespace App\Actions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ProcessSubscriptions
{
    /** Tagihan periode berikutnya terbit sekian hari sebelum periode berjalan habis. */
    public const RENEWAL_LEAD_DAYS = 7;

    public function __construct(
        private IssueSubscriptionInvoice $issue,
        private ApplySubscriptionToTenant $apply,
        private StartFreeSubscription $startFree,
        private SuspendTenant $suspend,
    ) {}

    /**
     * Putaran harian untuk satu tenant (LGN-03): selesaikan percobaan, terbitkan tagihan perpanjangan,
     * tandai menunggak, lalu tangguhkan setelah masa tenggang. Tiap langkah idempoten, jadi aman diulang.
     */
    public function handle(Tenant $tenant): void
    {
        TenantContext::instance()->runAs($tenant, function () use ($tenant) {
            DB::transaction(function () use ($tenant) {
                $subscription = Subscription::query()
                    ->whereIn('status', array_map(fn (SubscriptionStatus $s) => $s->value, SubscriptionStatus::current()))
                    ->latest('id')->lockForUpdate()->first();

                if ($subscription === null || $subscription->plan->isFree()) {
                    return;
                }

                if ($subscription->status === SubscriptionStatus::Trialing) {
                    if ($subscription->trial_ends_at <= now()) {
                        $this->finishTrial($tenant, $subscription);
                    }

                    return;
                }

                $today = BillingClock::today($tenant);

                $this->issueRenewal($subscription, $today);
                $this->markPastDue($subscription, $today);
                $this->suspendAfterGrace($tenant, $subscription);
            });
        });
    }

    /** Dibayar: aktif. Belum dibayar: percobaan berakhir dan tenant kembali ke Gratis tanpa penangguhan. */
    private function finishTrial(Tenant $tenant, Subscription $subscription): void
    {
        $paid = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionInvoiceStatus::Paid->value)
            ->orderBy('period_start')->first();

        if ($paid !== null) {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $paid->period_start,
                'current_period_end' => $paid->period_end,
            ])->save();
            $this->apply->handle($tenant, $subscription);

            return;
        }

        SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->get()
            ->each(fn (SubscriptionInvoice $invoice) => $invoice->forceFill(['status' => SubscriptionInvoiceStatus::Void])->save());

        $subscription->forceFill(['status' => SubscriptionStatus::Expired])->save();
        $this->startFree->handle($tenant);
    }

    private function issueRenewal(Subscription $subscription, CarbonImmutable $today): void
    {
        $periodEnd = CarbonImmutable::parse($subscription->current_period_end->toDateString());

        if ($today->lt($periodEnd->subDays(self::RENEWAL_LEAD_DAYS))) {
            return;
        }

        $next = $periodEnd->addDay();

        // issue() mengembalikan tagihan yang sudah ada bila periode itu sudah ditagih atau dibayar.
        $this->issue->handle($subscription, $next, $periodEnd);
    }

    private function markPastDue(Subscription $subscription, CarbonImmutable $today): void
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return;
        }

        $overdue = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->whereDate('due_date', '<', $today->toDateString())
            ->exists();

        if ($overdue) {
            $subscription->forceFill([
                'status' => SubscriptionStatus::PastDue,
                'grace_ends_at' => now()->addDays((int) config('kostra.subscription.grace_days')),
            ])->save();
        }
    }

    private function suspendAfterGrace(Tenant $tenant, Subscription $subscription): void
    {
        if ($subscription->status !== SubscriptionStatus::PastDue || $subscription->grace_ends_at === null || $subscription->grace_ends_at > now()) {
            return;
        }

        $subscription->forceFill(['status' => SubscriptionStatus::Expired])->save();
        $this->suspend->handle($tenant);
    }
}
