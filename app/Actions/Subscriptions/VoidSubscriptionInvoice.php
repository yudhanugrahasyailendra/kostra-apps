<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\SubscriptionInvoice;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidSubscriptionInvoice
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Membatalkan tagihan langganan yang belum dibayar. Tagihan lunas tidak bisa dibatalkan; tagihan tidak dihapus.
     */
    public function __invoke(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        return $this->context->run($invoice->tenant_id, fn () => DB::transaction(function () use ($invoice) {
            $locked = SubscriptionInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status !== SubscriptionInvoiceStatus::Pending) {
                throw ValidationException::withMessages(['general' => 'Hanya tagihan yang menunggu pembayaran yang bisa dibatalkan.']);
            }

            $locked->update(['status' => SubscriptionInvoiceStatus::Void]);

            return $locked;
        }));
    }
}
