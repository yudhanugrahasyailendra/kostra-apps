<?php

namespace App\Contracts;

use App\Models\Invoice;

interface InvoicePaidTotal
{
    /**
     * Jumlah yang sudah dibayar untuk tagihan ini, dari alokasi pembayaran berstatus aktif.
     */
    public function forInvoice(Invoice $invoice): int;
}
