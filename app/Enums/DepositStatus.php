<?php

namespace App\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum diterima',
            self::Held => 'Tercatat',
            self::Refunded => 'Dikembalikan',
            self::PartiallyRefunded => 'Dikembalikan sebagian',
            self::Forfeited => 'Hangus',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'menunggu',
            self::Held => 'lunas',
            self::Refunded, self::PartiallyRefunded, self::Forfeited => 'kosong',
        };
    }
}
