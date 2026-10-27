<?php

namespace App\Services\Messaging;

final class StatusUpdate
{
    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $providerMessageId,
        public readonly string $status,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
