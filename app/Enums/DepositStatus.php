<?php

namespace App\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Forfeited = 'forfeited';

    /** Istilah "tercatat": Kostra tidak menahan dana deposit, hanya mencatat kewajiban pemilik. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum diterima',
            self::Held => 'Tercatat diterima',
            self::Refunded => 'Dikembalikan penuh',
            self::PartiallyRefunded => 'Dikembalikan sebagian',
            self::Forfeited => 'Hangus (dipotong seluruhnya)',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Held => 'ok',
            self::Refunded, self::PartiallyRefunded => 'neutral',
            self::Forfeited => 'danger',
        };
    }
}
