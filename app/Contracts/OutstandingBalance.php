<?php

namespace App\Contracts;

use App\Models\Lease;

interface OutstandingBalance
{
    /**
     * Sisa tagihan kontrak yang belum dibayar, dalam rupiah bulat.
     */
    public function forLease(Lease $lease): int;
}
