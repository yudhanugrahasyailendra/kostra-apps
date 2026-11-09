<?php

namespace App\Services\WhatsApp;

final readonly class SendResult
{
    private function __construct(
        public bool $ok,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $retryable = false,
    ) {}

    public static function success(string $providerMessageId): self
    {
        return new self(true, $providerMessageId);
    }

    /** @param  bool  $retryable  true untuk galat sementara (jaringan, batas laju); false untuk penolakan permanen. */
    public static function failure(string $errorCode, string $errorMessage, bool $retryable = true): self
    {
        return new self(false, null, $errorCode, $errorMessage, $retryable);
    }
}
