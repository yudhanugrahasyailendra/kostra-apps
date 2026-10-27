<?php

namespace App\Services\Billing;

use App\Contracts\InvoicePaidTotal;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\PaymentAllocation;

class AllocationInvoicePaidTotal implements InvoicePaidTotal
{
    /**
     * Jumlah alokasi dari pembayaran yang berstatus aktif. Pembayaran yang dibatalkan tidak dihitung,
     * sehingga membatalkan pembayaran otomatis mengembalikan sisa tagihan.
     */
    public function forInvoice(Invoice $invoice): int
    {
        return (int) PaymentAllocation::query()
            ->where('invoice_id', $invoice->getKey())
            ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Active->value))
            ->sum('amount');
    }
}
