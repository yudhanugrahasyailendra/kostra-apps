<?php

namespace App\Services\Messaging;

final class SendResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerMessageId,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly bool $retryable,
    ) {}

    public static function sent(string $providerMessageId): self
    {
        return new self(true, $providerMessageId, null, null, false);
    }

    /**
     * @param  bool  $retryable  true untuk gangguan sementara (jaringan, batas laju); false untuk penolakan tetap (nomor tidak valid).
     */
    public static function failed(string $errorCode, string $errorMessage, bool $retryable = true): self
    {
        return new self(false, null, $errorCode, $errorMessage, $retryable);
    }
}
