<?php

namespace App\Enums;

/**
 * Izin tambahan untuk staf (users.permissions). Pemilik selalu punya semuanya.
 */
enum Permission: string
{
    case FinanceView = 'finance.view';
    case SettingsManage = 'settings.manage';
    case DocumentsView = 'documents.view';
    case PaymentsVoid = 'payments.void';

    public function label(): string
    {
        return match ($this) {
            self::FinanceView => 'Melihat keuangan (pengeluaran dan laporan)',
            self::SettingsManage => 'Mengelola pengaturan',
            self::DocumentsView => 'Mengelola dan melihat dokumen sensitif (foto KTP)',
            self::PaymentsVoid => 'Membatalkan pembayaran',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
