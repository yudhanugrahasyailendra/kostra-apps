<?php

namespace App\Enums;

enum BillingCycle: string
{
    // Hanya bulanan pada MVP; harian, mingguan, dan tahunan menyusul.
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
        };
    }
}
