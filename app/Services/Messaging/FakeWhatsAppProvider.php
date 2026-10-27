<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppProvider;
use Illuminate\Support\Str;

/**
 * Penyedia palsu untuk pengembangan lokal dan tes: tidak pernah menghubungi jaringan. Pesan yang "terkirim"
 * hanya disimpan di memori proses, dan kegagalan bisa diskenariokan.
 */
class FakeWhatsAppProvider implements WhatsAppProvider
{
    /** @var list<OutboundMessage> */
    private array $sent = [];

    private int $failuresLeft = 0;

    private ?SendResult $failure = null;

    public function name(): string
    {
        return 'fake';
    }

    public function send(OutboundMessage $message): SendResult
    {
        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            return $this->failure;
        }

        $this->sent[] = $message;

        return SendResult::sent('fake-'.Str::lower(Str::random(12)));
    }

    public function parseStatusWebhook(array $payload): ?StatusUpdate
    {
        $id = $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if (! is_string($id) || $id === '' || ! in_array($status, [StatusUpdate::DELIVERED, StatusUpdate::FAILED], true)) {
            return null;
        }

        return new StatusUpdate(
            $id,
            $status,
            isset($payload['error_code']) ? (string) $payload['error_code'] : null,
            isset($payload['error_message']) ? (string) $payload['error_message'] : null,
        );
    }

    /**
     * Skenario tes: $times pengiriman berikutnya gagal.
     */
    public function failNext(int $times = 1, string $code = 'fake_error', bool $retryable = true): self
    {
        $this->failuresLeft = $times;
        $this->failure = SendResult::failed($code, 'Kegagalan buatan dari penyedia palsu.', $retryable);

        return $this;
    }

    /**
     * @return list<OutboundMessage>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function reset(): void
    {
        $this->sent = [];
        $this->failuresLeft = 0;
        $this->failure = null;
    }
}
