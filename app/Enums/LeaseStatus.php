<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Ended => 'Selesai',
            self::Canceled => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Ended => 'neutral',
            self::Canceled => 'danger',
        };
    }
}
