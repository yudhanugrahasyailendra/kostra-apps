<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    // Penyimpangan dari docs/database.md: pengajuan berlangganan yang belum dibayar. Paket belum dibuka;
    // baru berjalan setelah Super Admin mencatat transfer (bayar dulu, tidak ada paket berbayar di muka).
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran pertama',
            self::Trialing => 'Percobaan',
            self::Active => 'Aktif',
            self::PastDue => 'Lewat jatuh tempo',
            self::Canceled => 'Dibatalkan',
            self::Expired => 'Berakhir',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Pending, self::Trialing, self::PastDue => 'warn',
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
