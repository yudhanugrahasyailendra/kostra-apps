<?php

namespace App\Contracts;

use App\Services\Messaging\OutboundMessage;
use App\Services\Messaging\SendResult;
use App\Services\Messaging\StatusUpdate;

/**
 * Lapisan penyedia WhatsApp. Penyedia baru cukup menambah satu kelas adapter dan konfigurasi
 * (config/kostra.php, kunci whatsapp.provider); modul lain tidak berubah.
 */
interface WhatsAppProvider
{
    /**
     * Nama penyedia yang tersimpan di message_logs.provider.
     */
    public function name(): string;

    /**
     * Mengirim satu pesan. Tidak melempar untuk kegagalan penyedia: kembalikan SendResult::failed().
     */
    public function send(OutboundMessage $message): SendResult;

    /**
     * Membaca muatan webhook status penyedia. Null bila bukan pembaruan status yang dikenal.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseStatusWebhook(array $payload): ?StatusUpdate;
}
