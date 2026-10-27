<?php

namespace App\Services\Billing;

use App\Contracts\InvoicePaidTotal;
use App\Models\Invoice;

/**
 * Implementasi sementara Fase 5: belum ada pembayaran, jadi terbayar selalu 0.
 * Diganti dengan perhitungan dari payment_allocations di Fase 6.
 */
class NullInvoicePaidTotal implements InvoicePaidTotal
{
    public function forInvoice(Invoice $invoice): int
    {
        return 0;
    }
}
