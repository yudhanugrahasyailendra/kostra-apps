<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Vacant = 'vacant';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Vacant => 'Kosong',
            self::Occupied => 'Terisi',
            self::Maintenance => 'Perawatan',
        };
    }

    /** Nada StatusBadge di sisi tampilan. */
    public function tone(): string
    {
        return match ($this) {
            self::Vacant => 'kosong',
            self::Occupied => 'lunas',
            self::Maintenance => 'menunggu',
        };
    }

    /**
     * Status yang boleh dipilih manual. `occupied` ditentukan kontrak aktif (Fase 4).
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Vacant, self::Maintenance];
    }
}
