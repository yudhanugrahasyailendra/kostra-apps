<?php

namespace App\Services\Messaging;

final class OutboundMessage
{
    public function __construct(
        public readonly int $logId,
        public readonly string $to,
        public readonly string $body,
        public readonly ?string $providerTemplateName = null,
    ) {}
}
