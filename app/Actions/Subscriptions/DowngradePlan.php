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
     * (data tidak pernah dihapus untuk memuat paket). Turun ke paket berbayar yang lebih murah hanya dari langganan
     * AKTIF dan mempertahankan periode berjalan yang sudah dibayar; dari percobaan atau langganan yang lewat jatuh
     * tempo hanya boleh turun ke Gratis, supaya periode yang belum dibayar tidak disalin menjadi akses gratis.
     * Tidak ada pengembalian dana (MVP).
     */
    public function __invoke(Tenant $tenant, Plan $plan): Subscription
    {
        return $this->context->run($tenant, function () use ($tenant, $plan) {
            $currentPlan = Plan::query()->findOrFail(Tenant::query()->whereKey($tenant->getKey())->value('plan_id'));

            if ($plan->price_monthly >= $currentPlan->price_monthly) {
                throw ValidationException::withMessages(['plan' => 'Pilih paket yang lebih murah dari paket sekarang.']);
            }

            $current = Subscription::query()->current()->latest('id')->first();

            if ($plan->price_monthly === 0) {
                $options = ['status' => SubscriptionStatus::Active, 'period_end' => null];
            } else {
                if ($current === null || $current->status !== SubscriptionStatus::Active || $current->current_period_end === null) {
                    throw ValidationException::withMessages(['plan' => 'Turun ke paket berbayar hanya dari langganan yang aktif. Selesaikan pembayaran dulu, atau turun ke paket Gratis.']);
                }

                $options = ['status' => SubscriptionStatus::Active, 'period_start' => $current->current_period_start, 'period_end' => $current->current_period_end];
            }

            // Pemeriksaan batas dilakukan ChangeSubscriptionPlan (assertFits) sebelum apa pun berubah.
            $new = ($this->change)($tenant, $plan, $current?->billing_period ?? BillingPeriod::Monthly, $options);

            // Tagihan dan pengajuan yang belum dibayar untuk paket lama tidak berlaku lagi.
            SubscriptionInvoice::query()
                ->where('status', SubscriptionInvoiceStatus::Pending->value)
                ->get()->each->update(['status' => SubscriptionInvoiceStatus::Void]);

            Subscription::query()->pending()->get()->each->update(['status' => SubscriptionStatus::Expired]);

            return $new;
        });
    }
}
