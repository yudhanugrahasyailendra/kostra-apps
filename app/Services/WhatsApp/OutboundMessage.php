<?php

namespace App\Services\WhatsApp;

/** Pesan yang akan dikirim. Nomor dan teks tidak boleh ditulis ke log aplikasi. */
final readonly class OutboundMessage
{
    public function __construct(
        public string $recipient,
        public string $body,
        public ?string $providerTemplateName = null,
        public ?int $logId = null,
    ) {}
}
