<?php

namespace App\Actions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSubscriptionPayment
{
    public function __construct(private ApplySubscriptionToTenant $apply) {}

    /**
     * Super Admin mencatat transfer langganan (LGN-04). Tagihan dicari di dalam tenant yang dituju, jadi tagihan
     * milik tenant lain tidak pernah ditemukan. Selama percobaan, tagihan hanya ditandai lunas dan langganan
     * menjadi aktif saat percobaan berakhir. Untuk langganan yang menunggak atau berakhir karena tunggakan,
     * pembayaran mengaktifkannya lagi dan mencabut penangguhan akibat tunggakan itu.
     *
     * @param  array{payment_method: string, reference?: string|null}  $data
     */
    public function handle(Tenant $tenant, int $invoiceId, array $data): SubscriptionInvoice
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $invoiceId, $data) {
            return DB::transaction(function () use ($tenant, $invoiceId, $data) {
                $invoice = SubscriptionInvoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

                if ($invoice->status !== SubscriptionInvoiceStatus::Pending) {
                    throw ValidationException::withMessages(['invoice' => 'Tagihan ini sudah lunas atau dibatalkan.']);
                }

                $invoice->forceFill([
                    'status' => SubscriptionInvoiceStatus::Paid,
                    'paid_at' => now(),
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['reference'] ?? null,
                ])->save();

                $subscription = Subscription::query()->whereKey($invoice->subscription_id)->lockForUpdate()->firstOrFail();
                $subscription->setRelation('plan', $subscription->plan()->first());

                if (in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Expired], true)) {
                    $wasExpired = $subscription->status === SubscriptionStatus::Expired;

                    $subscription->forceFill([
                        'status' => SubscriptionStatus::Active,
                        'current_period_start' => $invoice->period_start,
                        'current_period_end' => $invoice->period_end,
                        'grace_ends_at' => null,
                    ])->save();

                    if ($wasExpired && $tenant->status === TenantStatus::Suspended) {
                        $tenant->forceFill(['status' => TenantStatus::Active, 'suspended_at' => null]);
                    }

                    $this->apply->handle($tenant, $subscription);
                }

                return $invoice;
            });
        });
    }
}
