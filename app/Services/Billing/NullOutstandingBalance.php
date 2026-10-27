<?php

namespace App\Services\Billing;

use App\Contracts\OutstandingBalance;
use App\Models\Lease;

/**
 * Implementasi sementara Fase 4: belum ada tagihan, jadi sisa tagihan selalu 0.
 * Diganti dengan perhitungan dari invoices di Fase 5.
 */
class NullOutstandingBalance implements OutstandingBalance
{
    public function forLease(Lease $lease): int
    {
        return 0;
    }
}
