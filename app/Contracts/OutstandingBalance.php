<?php

namespace App\Contracts;

use App\Models\Lease;

/**
 * Sisa tagihan yang belum dibayar untuk satu kontrak, dalam rupiah bulat.
 * Dipakai saat check-out. Implementasi nyata (dari invoices) hadir di Fase 5.
 */
interface OutstandingBalance
{
    public function forLease(Lease $lease): int;
}
