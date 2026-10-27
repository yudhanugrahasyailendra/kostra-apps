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
use Carbon\CarbonImmutable;

class RunSubscriptionLifecycle
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateFreeSubscription $createFree,
        private readonly ChangeSubscriptionPlan $change,
        private readonly CreateSubscriptionInvoice $invoices,
        private readonly SuspendTenant $suspend,
    ) {}

    /**
     * Langkah harian per tenant (aman dijalankan ulang):
     *
     * - Percobaan habis tanpa berlangganan -> kembali ke Gratis (bukan ditangguhkan).
     * - Langganan aktif mendekati akhir periode -> tagihan perpanjangan dibuat.
     * - Periode lewat tanpa pembayaran -> status menunggu pembayaran dengan masa tenggang 7 hari; paket tetap berjalan.
     * - Masa tenggang habis: yang pernah membayar ditangguhkan (baca dan ekspor saja); yang belum pernah membayar
     *   (berlangganan lalu tidak membayar) kembali ke Gratis.
     */
    public function __invoke(Tenant $tenant): void
    {
        $this->context->run($tenant, function () use ($tenant) {
            $this->expireStaleRequests($tenant);

            $subscription = Subscription::query()->current()->latest('id')->first() ?? ($this->createFree)($tenant);

            match ($subscription->status) {
                SubscriptionStatus::Trialing => $this->checkTrial($tenant, $subscription),
                SubscriptionStatus::Active => $this->checkActive($tenant, $subscription),
                SubscriptionStatus::PastDue => $this->checkPastDue($tenant, $subscription),
                default => null,
            };
        });
    }

    /**
     * Pengajuan berlangganan yang tagihannya lewat jatuh tempo tanpa pembayaran dibatalkan. Paket tenant tidak
     * pernah berubah untuk pengajuan, jadi tidak ada yang perlu dikembalikan.
     */
    private function expireStaleRequests(Tenant $tenant): void
    {
        $today = $tenant->today()->toDateString();

        Subscription::query()->pending()->get()->each(function (Subscription $request) use ($today) {
            $stale = SubscriptionInvoice::query()
                ->where('subscription_id', $request->getKey())
                ->where('status', SubscriptionInvoiceStatus::Pending->value)
                ->where('due_date', '<', $today)
                ->get();

            if ($stale->isEmpty()) {
                return;
            }

            $stale->each->update(['status' => SubscriptionInvoiceStatus::Void]);
            $request->update(['status' => SubscriptionStatus::Expired]);
        });
    }

    private function checkTrial(Tenant $tenant, Subscription $subscription): void
    {
        if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->lessThanOrEqualTo(now())) {
            $this->toFree($tenant, SubscriptionStatus::Expired);
        }
    }

    private function checkActive(Tenant $tenant, Subscription $subscription): void
    {
        if ($subscription->isFree() || $subscription->current_period_end === null) {
            return;
        }

        $today = $tenant->today();
        $end = CarbonImmutable::parse($subscription->current_period_end->toDateString(), $tenant->timezone)->startOfDay();
        $nextStart = $end->addDay();

        $hasNextInvoice = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('period_start', $nextStart->toDateString())
            ->whereIn('status', [SubscriptionInvoiceStatus::Pending->value, SubscriptionInvoiceStatus::Paid->value])
            ->exists();

        if (! $hasNextInvoice && $today->greaterThanOrEqualTo($end->subDays((int) config('kostra.renewal_notice_days')))) {
            ($this->invoices)($subscription, $nextStart, $end);
        }

        if ($today->greaterThan($end)) {
            $subscription->update([
                'status' => SubscriptionStatus::PastDue,
                'grace_ends_at' => $end->addDays((int) config('kostra.grace_days') + 1)->utc(),
            ]);
        }
    }

    private function checkPastDue(Tenant $tenant, Subscription $subscription): void
    {
        if ($subscription->grace_ends_at === null || $subscription->grace_ends_at->greaterThan(now())) {
            return;
        }

        $everPaid = SubscriptionInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', SubscriptionInvoiceStatus::Paid->value)
            ->exists();

        if ($everPaid) {
            ($this->suspend)($tenant, SuspendTenant::REASON_ARREARS);

            return;
        }

        $this->toFree($tenant, SubscriptionStatus::Expired);
    }

    private function toFree(Tenant $tenant, SubscriptionStatus $closeAs): void
    {
        $free = Plan::query()->where('key', 'free')->firstOrFail();

        // Data yang melebihi batas Gratis tidak dihapus; hanya penambahan baru yang tertahan (PlanLimits).
        ($this->change)($tenant, $free, BillingPeriod::Monthly, [
            'status' => SubscriptionStatus::Active,
            'period_end' => null,
            'close_as' => $closeAs,
        ], checkLimits: false);

        SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->get()->each->update(['status' => SubscriptionInvoiceStatus::Void]);
    }
}
