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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSubscription
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateSubscriptionInvoice $invoices,
    ) {}

    /**
     * Pemilik berlangganan paket berbayar (atau naik paket). BAYAR DULU: yang dibuat hanya pengajuan (status
     * pending) dan tagihan; paket tenant TIDAK berubah sampai Super Admin mencatat transfer
     * (RecordSubscriptionPayment). Dengan begitu paket berbayar tidak pernah terbuka tanpa pembayaran, dan siklus
     * ajukan-lalu-biarkan-lapuk tidak memberi akses gratis. Satu-satunya akses tanpa bayar adalah masa percobaan
     * 14 hari, sekali per tenant (StartTrial).
     *
     * Naik paket di tengah periode menagih satu periode penuh, tanpa prorata. Seluruh pemeriksaan dan pembuatan
     * berada dalam satu transaksi dengan kunci pada tenant, jadi dua permintaan bersamaan tidak membuat dua tagihan.
     */
    public function __invoke(Tenant $tenant, Plan $plan, BillingPeriod $period): SubscriptionInvoice
    {
        if ($plan->price_monthly <= 0 || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Pilih paket berbayar yang tersedia.']);
        }

        if ($period === BillingPeriod::Yearly && $plan->price_yearly === null) {
            throw ValidationException::withMessages(['plan' => "Paket {$plan->name} tidak punya penagihan tahunan."]);
        }

        return $this->context->run($tenant, fn () => DB::transaction(function () use ($tenant, $plan, $period) {
            $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $current = Plan::query()->findOrFail($locked->plan_id);
            $subscription = Subscription::query()->current()->latest('id')->first();

            if (SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::Pending->value)->exists()) {
                throw ValidationException::withMessages(['plan' => 'Masih ada tagihan langganan yang menunggu pembayaran. Selesaikan atau minta Super Admin membatalkannya dulu.']);
            }

            if ($current->getKey() === $plan->getKey() && $subscription?->status === SubscriptionStatus::Active) {
                throw ValidationException::withMessages(['plan' => "Anda sudah berlangganan paket {$plan->name}."]);
            }

            if ($current->price_monthly > $plan->price_monthly && $subscription?->status === SubscriptionStatus::Active) {
                throw ValidationException::withMessages(['plan' => 'Untuk pindah ke paket yang lebih murah, gunakan turun paket.']);
            }

            $today = $locked->today();

            $pending = Subscription::create([
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Pending,
                'billing_period' => $period,
                'current_period_start' => $today->toDateString(),
                'current_period_end' => $today->addMonthsNoOverflow($period->months())->subDay()->toDateString(),
            ]);
            $pending->setRelation('plan', $plan);

            return ($this->invoices)($pending, $today, $today->addDays((int) config('kostra.grace_days')));
        }));
    }
}
