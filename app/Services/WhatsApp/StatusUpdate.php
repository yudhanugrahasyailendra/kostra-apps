<?php

namespace App\Services\WhatsApp;

use App\Enums\MessageStatus;

/** Pembaruan status dari penyedia: hanya delivered atau failed yang bermakna setelah pesan terkirim. */
final readonly class StatusUpdate
{
    public function __construct(
        public string $providerMessageId,
        public MessageStatus $status,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}
}
