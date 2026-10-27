<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSubscriptionPayment
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Super Admin mencatat transfer langganan yang sudah diterima.
     *
     * - Pengajuan baru (status pending): paket dibuka SEKARANG. Langganan lama ditutup, dan periode mulai dari
     *   tanggal pembayaran (tagihan disesuaikan), jadi menunda transfer tidak memajukan masa berlaku.
     * - Perpanjangan atau langganan berjalan: periode menjadi periode tagihan itu.
     *
     * Tenant yang ditangguhkan karena TUNGGAKAN dibuka kembali; yang ditangguhkan Super Admin (manual, atau tanpa
     * alasan tercatat) tetap ditangguhkan: pembayaran tidak membatalkan keputusan itu.
     */
    public function __invoke(SubscriptionInvoice $invoice, string $method, ?string $reference, ?CarbonInterface $paidAt, User $by): SubscriptionInvoice
    {
        $method = trim($method);

        if ($method === '' || mb_strlen($method) > 100) {
            throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran wajib diisi (maksimal 100 karakter).']);
        }

        $reference = $reference === null ? null : mb_substr(trim($reference), 0, 100);

        return $this->context->run($invoice->tenant_id, function () use ($invoice, $method, $reference, $paidAt) {
            return DB::transaction(function () use ($invoice, $method, $reference, $paidAt) {
                $locked = SubscriptionInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

                if ($locked->status !== SubscriptionInvoiceStatus::Pending) {
                    throw ValidationException::withMessages(['general' => 'Tagihan ini sudah dibayar atau dibatalkan.']);
                }

                $tenant = Tenant::query()->whereKey($locked->tenant_id)->lockForUpdate()->firstOrFail();
                $subscription = Subscription::query()->lockForUpdate()->findOrFail($locked->subscription_id);

                if (! in_array($subscription->status, [SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Trialing], true)) {
                    throw ValidationException::withMessages(['general' => 'Tagihan ini bukan untuk langganan yang sedang berjalan.']);
                }

                if ($subscription->status === SubscriptionStatus::Pending) {
                    $this->activateRequest($tenant, $subscription, $locked);
                } else {
                    $subscription->update([
                        'status' => SubscriptionStatus::Active,
                        'current_period_start' => $locked->period_start->toDateString(),
                        'current_period_end' => $locked->period_end->toDateString(),
                        'grace_ends_at' => null,
                        'trial_ends_at' => null,
                    ]);
                }

                $locked->update([
                    'status' => SubscriptionInvoiceStatus::Paid,
                    'paid_at' => $paidAt ?? now(),
                    'payment_method' => $method,
                    'reference' => $reference,
                ]);

                $this->syncTenant($tenant, $subscription);

                return $locked;
            });
        });
    }

    private function activateRequest(Tenant $tenant, Subscription $request, SubscriptionInvoice $invoice): void
    {
        Subscription::query()->current()->get()->each(fn (Subscription $old) => $old->update([
            'status' => $old->status === SubscriptionStatus::Trialing ? SubscriptionStatus::Expired : SubscriptionStatus::Canceled,
            'canceled_at' => $old->status === SubscriptionStatus::Trialing ? $old->canceled_at : now(),
        ]));

        $start = $tenant->today();
        $end = $start->addMonthsNoOverflow($request->billing_period->months())->subDay();

        $invoice->update(['period_start' => $start->toDateString(), 'period_end' => $end->toDateString()]);

        $request->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start->toDateString(),
            'current_period_end' => $end->toDateString(),
            'grace_ends_at' => null,
            'trial_ends_at' => null,
        ]);
    }

    private function syncTenant(Tenant $tenant, Subscription $subscription): void
    {
        $settings = $tenant->settings ?? [];
        $arrears = $tenant->status === TenantStatus::Suspended && ($settings['suspension_reason'] ?? null) === SuspendTenant::REASON_ARREARS;
        $reopen = $tenant->status !== TenantStatus::Suspended || $arrears;

        $attributes = ['plan_id' => $subscription->plan_id, 'trial_ends_at' => null];

        if ($tenant->status !== TenantStatus::Closed && $reopen) {
            unset($settings['suspension_reason']);

            $attributes += [
                'status' => TenantStatus::Active,
                'suspended_at' => null,
                'settings' => $settings === [] ? null : $settings,
            ];
        }

        $tenant->update($attributes);
    }
}
