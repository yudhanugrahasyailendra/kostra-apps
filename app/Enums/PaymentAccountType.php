<?php

namespace App\Enums;

enum PaymentAccountType: string
{
    case Bank = 'bank';
    case Qris = 'qris';
    case Ewallet = 'ewallet';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Rekening bank',
            self::Qris => 'QRIS statis',
            self::Ewallet => 'Dompet digital',
        };
    }
}
