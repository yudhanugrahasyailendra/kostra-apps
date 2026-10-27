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

    /**
     * Nama kelas warna badge (lihat resources/css/app.css).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Vacant => 'neutral',
            self::Occupied => 'ok',
            self::Maintenance => 'maintenance',
        };
    }

    /**
     * Status yang boleh dipilih pemilik secara manual. Occupied ditentukan kontrak aktif (Fase 4).
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Vacant, self::Maintenance];
    }

    /**
     * @return list<string>
     */
    public static function manualValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::manual());
    }
}
