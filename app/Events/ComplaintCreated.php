<?php

namespace App\Events;

/**
 * Tiket baru dibuat (dikirim setelah transaksi selesai). Pemberi tahu pemilik mendengarkannya di Fase 9.
 */
class ComplaintCreated
{
    public function __construct(
        public readonly int $complaintId,
        public readonly int $tenantId,
    ) {}
}
