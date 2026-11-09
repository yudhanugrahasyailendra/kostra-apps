<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Ewallet = 'ewallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer bank',
            self::Qris => 'QRIS',
            self::Ewallet => 'Dompet digital',
            self::Other => 'Lainnya',
        };
    }
}
