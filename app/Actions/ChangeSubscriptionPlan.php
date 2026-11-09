<?php

namespace App\Actions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeSubscriptionPlan
{
    public function __construct(
        private PlanLimits $limits,
        private IssueSubscriptionInvoice $issue,
        private ApplySubscriptionToTenant $apply,
        private StartSubscriptionTrial $startTrial,
        private StartFreeSubscription $startFree,
    ) {}

    /**
     * Naik atau turun paket (LGN-05). Berlaku langsung: batas dan fitur paket baru terbuka saat itu juga.
     * Turun paket diperiksa terhadap properti, kamar, dan pengguna yang ada. Tagihan yang belum dibayar
     * dibatalkan lalu diterbitkan ulang dengan harga paket baru; periode yang sudah dibayar tidak diubah.
     */
    public function handle(Tenant $tenant, Plan $target, SubscriptionPeriod $period): Subscription
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $target, $period) {
            return DB::transaction(function () use ($tenant, $target, $period) {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();

                if (! $target->is_active) {
                    throw ValidationException::withMessages(['plan' => 'Paket ini tidak tersedia.']);
                }

                if (! $target->isFree() && $target->priceFor($period) === null) {
                    throw ValidationException::withMessages(['plan' => "Paket {$target->name} tidak dijual dengan periode {$period->label()}."]);
                }

                $current = $tenant->currentSubscription() ?? $this->startFree->handle($tenant);
                $current->load('plan');

                if ($current->plan_id === $target->id && ($target->isFree() || $current->billing_period === $period)) {
                    throw ValidationException::withMessages(['plan' => 'Anda sudah memakai paket ini.']);
                }

                // Paket yang sudah dibayar selama percobaan tidak diganti sendiri: tagihannya sudah lunas.
                if ($current->isTrialing() && $current->invoices()->where('status', SubscriptionInvoiceStatus::Paid->value)->exists()) {
                    throw ValidationException::withMessages(['plan' => 'Tagihan percobaan sudah dibayar. Ganti paket setelah masa percobaan berakhir.']);
                }

                $this->limits->assertFitsPlan($target);

                if ($target->isFree()) {
                    return $this->toFree($tenant, $current);
                }

                if ($current->plan->isFree()) {
                    return $this->fromFree($tenant, $current, $target, $period);
                }

                return $this->betweenPaid($tenant, $current, $target, $period);
            });
        });
    }

    private function toFree(Tenant $tenant, Subscription $current): Subscription
    {
        $this->voidPending($current);
        $current->forceFill(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now(), 'grace_ends_at' => null])->save();

        return $this->startFree->handle($tenant);
    }

    private function fromFree(Tenant $tenant, Subscription $current, Plan $target, SubscriptionPeriod $period): Subscription
    {
        if (StartSubscriptionTrial::eligible($tenant)) {
            return $this->startTrial->handle($tenant, $target, $period);
        }

        // Percobaan sudah terpakai: langsung berlangganan, tagihan pertama jatuh tempo dalam masa tenggang.
        $current->forceFill(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()])->save();

        $today = BillingClock::today($tenant);
        $subscription = new Subscription([
            'plan_id' => $target->id,
            'status' => SubscriptionStatus::Active,
            'billing_period' => $period,
            'current_period_start' => $today->toDateString(),
            'current_period_end' => $period->endFrom($today)->toDateString(),
        ]);
        $subscription->save();
        $subscription->setRelation('plan', $target);

        $this->issue->handle($subscription, $today, $today->addDays((int) config('kostra.subscription.grace_days')));
        $this->apply->handle($tenant, $subscription);

        return $subscription;
    }

    private function betweenPaid(Tenant $tenant, Subscription $current, Plan $target, SubscriptionPeriod $period): Subscription
    {
        $current->plan_id = $target->id;
        $current->billing_period = $period;
        $current->save();
        $current->setRelation('plan', $target);

        $this->reissuePending($current);
        $this->apply->handle($tenant, $current);

        return $current;
    }

    private function voidPending(Subscription $subscription): void
    {
        SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->get()
            ->each(fn (SubscriptionInvoice $invoice) => $invoice->forceFill(['status' => SubscriptionInvoiceStatus::Void])->save());
    }

    /** Batalkan tagihan yang belum dibayar dan terbitkan ulang untuk periode dan jatuh tempo yang sama dengan harga paket baru. */
    private function reissuePending(Subscription $subscription): void
    {
        $pending = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->get();

        foreach ($pending as $invoice) {
            $invoice->forceFill(['status' => SubscriptionInvoiceStatus::Void])->save();
            $this->issue->handle(
                $subscription,
                CarbonImmutable::parse($invoice->period_start->toDateString()),
                CarbonImmutable::parse($invoice->due_date->toDateString()),
            );
        }
    }
}
