<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Models\Payment;
use App\Tenancy\TenantContext;

/** Data kuitansi, dipakai halaman pemilik, PDF, dan portal penghuni agar isinya selalu sama. */
class ReceiptData
{
    /** @return array<string, mixed> */
    public function for(Payment $payment): array
    {
        $payment->load(['resident', 'account', 'recorder', 'allocations.invoice.lease.room', 'allocations.invoice.property']);

        $firstInvoice = $payment->allocations->first()?->invoice;
        $tenant = TenantContext::instance()->tenant();

        return [
            'number' => $payment->receipt_number,
            'issued_at' => $payment->receipt_issued_at?->toIso8601String(),
            'business_name' => $tenant?->name,
            'property_name' => $firstInvoice?->property?->name,
            'property_address' => $firstInvoice?->property?->address,
            'resident_name' => $payment->resident->name,
            'room_number' => $firstInvoice?->lease?->room?->number,
            'amount' => $payment->amount,
            'paid_on' => $payment->paid_on->toDateString(),
            'method_label' => $payment->method->label(),
            'reference' => $payment->reference,
            'account_label' => $payment->account?->displayName(),
            'account_name' => $payment->account?->account_name,
            'recorded_by_name' => $payment->recorder?->name,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'void_reason' => $payment->void_reason,
            'lines' => $payment->allocations->map(fn ($allocation) => [
                'invoice_number' => $allocation->invoice?->number,
                'period' => $allocation->invoice?->period,
                'amount' => $allocation->amount,
                'invoice_total' => $allocation->invoice?->total,
                'invoice_paid' => $allocation->invoice?->status === InvoiceStatus::Paid,
            ])->values()->all(),
        ];
    }
}
