<?php

namespace App\Events;

/**
 * Bukti transfer ditolak (dikirim setelah transaksi selesai). Pemberitahuan ke penghuni dikirim di Fase 9.
 */
class ProofRejected
{
    public function __construct(
        public readonly int $proofId,
        public readonly int $tenantId,
    ) {}
}
