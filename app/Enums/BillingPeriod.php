<?php

namespace App\Enums;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
