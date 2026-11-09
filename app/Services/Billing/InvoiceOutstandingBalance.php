<?php

namespace App\Services\Billing;

use App\Contracts\OutstandingBalance;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;

/** Sisa tagihan sebuah kontrak = jumlah (total - paid_total) tagihan terbit yang belum lunas. Draf dan batal tidak dihitung. */
class InvoiceOutstandingBalance implements OutstandingBalance
{
    public function forLease(Lease $lease): int
    {
        return (int) Invoice::query()
            ->where('lease_id', $lease->id)
            ->whereIn('status', InvoiceStatus::openValues())
            ->selectRaw('COALESCE(SUM(total - paid_total), 0) as outstanding')
            ->value('outstanding');
    }
}
