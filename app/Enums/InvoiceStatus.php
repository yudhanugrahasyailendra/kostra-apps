<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    // Draf ada di skema tetapi belum dipakai: nomor wajib terisi, jadi tagihan langsung terbit.
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
            self::Issued => 'Belum dibayar',
            self::Partial => 'Dibayar sebagian',
            self::Paid => 'Lunas',
            self::Overdue => 'Terlambat',
            self::Void => 'Batal',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Issued, self::Void => 'neutral',
            self::Partial => 'warn',
            self::Paid => 'ok',
            self::Overdue => 'danger',
        };
    }

    /**
     * Status yang masih menagih uang (belum lunas dan tidak batal).
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Issued->value, self::Partial->value, self::Overdue->value];
    }
}
