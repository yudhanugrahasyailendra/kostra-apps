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
            self::MovedOut => 'Penghuni pindah',
            self::Terminated => 'Diputus pemilik',
        };
    }

    /**
     * Alasan yang boleh dipilih saat check-out. "Expired" dipakai otomatis oleh perpanjangan.
     *
     * @return list<string>
     */
    public static function checkOutValues(): array
    {
        return [self::MovedOut->value, self::Terminated->value];
    }
}
