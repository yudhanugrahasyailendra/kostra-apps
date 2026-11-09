<?php

namespace App\Enums;

/**
 * Izin staf yang disimpan di users.permissions. Pemilik selalu punya semuanya.
 */
enum Permission: string
{
    case FinanceView = 'finance.view';
    case SettingsManage = 'settings.manage';
    case DataExport = 'data.export';
    case SensitiveFiles = 'files.sensitive';

    public function label(): string
    {
        return match ($this) {
            self::FinanceView => 'Melihat keuangan dan laporan',
            self::SettingsManage => 'Mengelola pengaturan',
            self::DataExport => 'Mengekspor data',
            self::SensitiveFiles => 'Membuka foto KTP dan dokumen sensitif',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
