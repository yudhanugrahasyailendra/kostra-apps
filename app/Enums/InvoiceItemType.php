<?php

namespace App\Enums;

enum InvoiceItemType: string
{
    case Rent = 'rent';
    case Utility = 'utility';
    case Service = 'service';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Sewa',
            self::Utility => 'Listrik / air',
            self::Service => 'Layanan',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Jenis yang boleh dipilih pada tagihan manual (sewa pokok hanya dari penjadwal).
     *
     * @return list<string>
     */
    public static function manualValues(): array
    {
        return [self::Utility->value, self::Service->value, self::Other->value, self::Rent->value];
    }
}
