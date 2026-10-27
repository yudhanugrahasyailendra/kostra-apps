<?php

namespace App\Events;

/**
 * Pembayaran tercatat (dikirim setelah transaksi selesai). Pengirim kuitansi lewat WhatsApp mendengarkannya di Fase 9.
 */
class PaymentRecorded
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $tenantId,
    ) {}
}
