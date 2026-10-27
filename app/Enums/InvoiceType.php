<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Recurring = 'recurring';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Recurring => 'Bulanan',
            self::Manual => 'Manual',
        };
    }
}
