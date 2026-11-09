<?php

namespace App\Services\WhatsApp;

use App\Enums\MessageStatus;

/**
 * Penyedia palsu untuk tes dan lingkungan lokal: tidak ada pesan sungguhan yang keluar (AGENTS.md).
 * Pesan hanya disimpan di memori proses; kegagalan bisa diprogram lewat failNext().
 */
class FakeWhatsAppProvider implements WhatsAppProvider
{
    /** @var list<OutboundMessage> */
    private array $sent = [];

    /** @var list<SendResult> */
    private array $failures = [];

    private int $counter = 0;

    public function name(): string
    {
        return 'fake';
    }

    public function send(OutboundMessage $message): SendResult
    {
        if ($this->failures !== []) {
            return array_shift($this->failures);
        }

        $this->sent[] = $message;

        return SendResult::success('fake-'.(++$this->counter));
    }

    public function parseStatusWebhook(array $payload): ?StatusUpdate
    {
        $status = MessageStatus::tryFrom((string) ($payload['status'] ?? ''));

        if (! isset($payload['message_id']) || ! in_array($status, [MessageStatus::Delivered, MessageStatus::Failed], true)) {
            return null;
        }

        return new StatusUpdate((string) $payload['message_id'], $status, $payload['error_code'] ?? null, $payload['error_message'] ?? null);
    }

    /** Buat $times pengiriman berikutnya gagal dengan kode galat ini. */
    public function failNext(int $times = 1, string $code = 'FAKE_ERROR', bool $retryable = true): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->failures[] = SendResult::failure($code, 'Kegagalan simulasi penyedia palsu.', $retryable);
        }
    }

    /** @return list<OutboundMessage> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function reset(): void
    {
        $this->sent = [];
        $this->failures = [];
        $this->counter = 0;
    }
}
