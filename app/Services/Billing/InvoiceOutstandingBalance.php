<?php

namespace App\Services\Billing;

use App\Contracts\OutstandingBalance;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;

class InvoiceOutstandingBalance implements OutstandingBalance
{
    /**
     * Total sisa (total - terbayar) seluruh tagihan kontrak yang masih terbuka.
     */
    public function forLease(Lease $lease): int
    {
        return (int) Invoice::query()
            ->where('lease_id', $lease->getKey())
            ->whereIn('status', InvoiceStatus::openValues())
            // CAST ke SIGNED: selisih BIGINT UNSIGNED yang negatif akan melempar galat di MySQL.
            ->selectRaw('COALESCE(SUM(CAST(total AS SIGNED) - CAST(paid_total AS SIGNED)), 0) as outstanding')
            ->value('outstanding');
    }
}
