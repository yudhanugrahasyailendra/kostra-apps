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
            self::Queued => 'Menunggu kirim',
            self::Sent => 'Terkirim',
            self::Delivered => 'Sampai',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'warn',
            self::Sent, self::Delivered => 'ok',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
        };
    }
}
