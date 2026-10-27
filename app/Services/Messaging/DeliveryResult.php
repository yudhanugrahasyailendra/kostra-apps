<?php

namespace App\Services\Messaging;

use Carbon\CarbonImmutable;

/**
 * Hasil satu upaya pengiriman: selesai (terkirim, dilewati, atau gagal permanen), ditunda ke jam kirim berikutnya,
 * atau dicoba ulang beberapa detik lagi.
 */
final class DeliveryResult
{
    private function __construct(public readonly ?int $releaseSeconds) {}

    public static function done(): self
    {
        return new self(null);
    }

    public static function deferredUntil(CarbonImmutable $at): self
    {
        return new self(max(1, (int) now()->diffInSeconds($at, true)));
    }

    public static function retryIn(int $seconds): self
    {
        return new self(max(1, $seconds));
    }

    public function isDone(): bool
    {
        return $this->releaseSeconds === null;
    }
}
