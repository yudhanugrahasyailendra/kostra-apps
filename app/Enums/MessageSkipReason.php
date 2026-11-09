<?php

namespace App\Enums;

enum MessageSkipReason: string
{
    case OptedOut = 'opted_out';
    case QuotaExceeded = 'quota_exceeded';
    case OutsideWindow = 'outside_window';

    public function label(): string
    {
        return match ($this) {
            self::OptedOut => 'Penghuni berhenti menerima pesan',
            self::QuotaExceeded => 'Kuota pesan habis',
            self::OutsideWindow => 'Di luar jam kirim',
        };
    }
}
