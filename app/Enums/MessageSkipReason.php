<?php

namespace App\Enums;

enum MessageSkipReason: string
{
    case OptedOut = 'opted_out';
    case QuotaExceeded = 'quota_exceeded';
    // Dalam praktik pesan di luar jam kirim ditunda (status queued dengan scheduled_for), bukan dilewati.
    // Nilai ini tersedia sesuai docs/database.md untuk penyedia yang menolak kirim di luar jam.
    case OutsideWindow = 'outside_window';

    public function label(): string
    {
        return match ($this) {
            self::OptedOut => 'Penghuni berhenti menerima pesan',
            self::QuotaExceeded => 'Kuota bulan ini habis',
            self::OutsideWindow => 'Di luar jam kirim',
        };
    }
}
