<?php

namespace App\Events;

/**
 * Tagihan baru terbit. Dikirim setelah transaksi selesai; pengirim pesan WhatsApp mendengarkannya di Fase 9.
 * Membawa id (bukan model) supaya aman diteruskan ke antrean.
 */
class InvoiceIssued
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $tenantId,
    ) {}
}
