<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Issued => 'Terbit',
            self::Partial => 'Sebagian dibayar',
            self::Paid => 'Lunas',
            self::Overdue => 'Lewat jatuh tempo',
            self::Void => 'Batal',
        };
    }

    /** Nada StatusBadge di sisi tampilan. */
    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'lunas',
            self::Issued, self::Partial => 'menunggu',
            self::Overdue => 'jatuh-tempo',
            self::Draft, self::Void => 'kosong',
        };
    }

    /** Status tagihan yang masih menunggu pelunasan (dihitung sebagai sisa tagihan). */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Issued, self::Partial, self::Overdue];
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::open());
    }
}
