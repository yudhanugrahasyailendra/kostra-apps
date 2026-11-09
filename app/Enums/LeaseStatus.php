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
            self::Ended => 'Berakhir',
            self::Canceled => 'Dibatalkan',
        };
    }

    /** Nada StatusBadge di sisi tampilan. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'lunas',
            self::Ended, self::Canceled => 'kosong',
        };
    }
}
