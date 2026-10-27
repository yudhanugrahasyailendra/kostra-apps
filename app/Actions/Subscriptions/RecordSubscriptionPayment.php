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
     * Super Admin mencatat transfer langganan yang sudah diterima. Tagihan menjadi lunas, langganan aktif untuk
     * periode tagihan itu, dan tenant yang ditangguhkan karena tunggakan langganan dibuka kembali.
     * Hanya tagihan langganan yang sedang berjalan yang bisa dibayar.
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

                $subscription = Subscription::query()->lockForUpdate()->findOrFail($locked->subscription_id);

                if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Trialing], true)) {
                    throw ValidationException::withMessages(['general' => 'Tagihan ini bukan untuk langganan yang sedang berjalan.']);
                }

                $locked->update([
                    'status' => SubscriptionInvoiceStatus::Paid,
                    'paid_at' => $paidAt ?? now(),
                    'payment_method' => $method,
                    'reference' => $reference,
                ]);

                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => $locked->period_start->toDateString(),
                    'current_period_end' => $locked->period_end->toDateString(),
                    'grace_ends_at' => null,
                    'trial_ends_at' => null,
                ]);

                $tenant = Tenant::query()->whereKey($locked->tenant_id)->lockForUpdate()->firstOrFail();

                $tenant->update([
                    'plan_id' => $subscription->plan_id,
                    'status' => $tenant->status === TenantStatus::Closed ? TenantStatus::Closed : TenantStatus::Active,
                    'trial_ends_at' => null,
                    'suspended_at' => $tenant->status === TenantStatus::Closed ? $tenant->suspended_at : null,
                ]);

                return $locked;
            });
        });
    }
}
