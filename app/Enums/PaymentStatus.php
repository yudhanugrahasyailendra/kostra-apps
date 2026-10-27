<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Voided => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Voided => 'danger',
        };
    }
}
