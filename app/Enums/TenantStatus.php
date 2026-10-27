<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Percobaan',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
            self::Closed => 'Ditutup',
        };
    }
}
