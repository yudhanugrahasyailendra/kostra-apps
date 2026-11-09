<?php

namespace App\Enums;

enum LeaseEndReason: string
{
    case Expired = 'expired';
    case MovedOut = 'moved_out';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Expired => 'Masa sewa habis',
            self::MovedOut => 'Penghuni pindah keluar',
            self::Terminated => 'Diakhiri lebih awal',
        };
    }
}
