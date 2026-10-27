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
            self::Trialing => 'Percobaan',
            self::Active => 'Aktif',
            self::PastDue => 'Menunggu pembayaran',
            self::Canceled => 'Dibatalkan',
            self::Expired => 'Berakhir',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Trialing, self::PastDue => 'warn',
            self::Canceled => 'neutral',
            self::Expired => 'danger',
        };
    }

    /**
     * Status langganan yang sedang berjalan (satu per tenant).
     *
     * @return list<string>
     */
    public static function currentValues(): array
    {
        return [self::Trialing->value, self::Active->value, self::PastDue->value];
    }
}
