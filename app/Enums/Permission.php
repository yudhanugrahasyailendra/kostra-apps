<?php

namespace App\Enums;

/**
 * Izin tambahan untuk staf (users.permissions). Pemilik selalu punya semuanya.
 */
enum Permission: string
{
    case FinanceView = 'finance.view';
    case SettingsManage = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::FinanceView => 'Melihat keuangan (pengeluaran dan laporan)',
            self::SettingsManage => 'Mengelola pengaturan',
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
