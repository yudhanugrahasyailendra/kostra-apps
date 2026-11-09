<?php

namespace App\Enums;

/** Siklus tagihan. MVP hanya bulanan; harian, mingguan, dan tahunan menyusul. */
enum BillingCycle: string
{
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
        };
    }
}
