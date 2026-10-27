<?php

namespace App\Events;

/**
 * Status tiket berubah (dikirim setelah transaksi selesai). Pemberi tahu penghuni mendengarkannya di Fase 9;
 * di Fase 8 tidak ada pesan yang dikirim.
 */
class ComplaintStatusChanged
{
    public function __construct(
        public readonly int $complaintId,
        public readonly int $tenantId,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
