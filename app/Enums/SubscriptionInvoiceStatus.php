<?php

namespace App\Enums;

enum SubscriptionInvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
            self::Void => 'Dibatalkan',
        };
    }
}
