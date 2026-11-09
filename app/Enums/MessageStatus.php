<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antre',
            self::Sent => 'Terkirim',
            self::Delivered => 'Diterima',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }

    /** Nada StatusBadge di sisi tampilan. */
    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'menunggu',
            self::Sent => 'kosong',
            self::Delivered => 'lunas',
            self::Failed => 'jatuh-tempo',
            self::Skipped => 'kosong',
        };
    }
}
