<?php

namespace App\Services\WhatsApp;

/**
 * Batas antara Kostra dan penyedia WhatsApp (NTF-01). Penyedia baru cukup menambah satu kelas yang
 * mengimplementasikan interface ini dan mendaftarkannya di config/kostra.php; modul lain tidak berubah.
 */
interface WhatsAppProvider
{
    /** Nama pendek yang disimpan di message_logs.provider. */
    public function name(): string;

    /** Kirim satu pesan. Tidak melempar untuk kegagalan penyedia; kembalikan SendResult::failure(). */
    public function send(OutboundMessage $message): SendResult;

    /**
     * Baca payload webhook status dari penyedia. Null bila payload bukan pembaruan status
     * atau tidak dikenali.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseStatusWebhook(array $payload): ?StatusUpdate;
}
