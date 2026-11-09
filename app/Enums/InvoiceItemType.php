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
            self::Utility => 'Listrik/air',
            self::Service => 'Layanan',
            self::Other => 'Lainnya',
        };
    }
}
