<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Masa percobaan',
            self::Active => 'Aktif',
            self::PastDue => 'Menunggak',
            self::Canceled => 'Dibatalkan',
            self::Expired => 'Berakhir',
        };
    }

    /** Status yang masih memberi hak paket kepada tenant. */
    public static function current(): array
    {
        return [self::Trialing, self::Active, self::PastDue];
    }
}
