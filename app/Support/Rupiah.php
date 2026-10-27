<?php

namespace App\Support;

class Rupiah
{
    /**
     * Baca input uang ("1.850.000", "Rp 1850000") menjadi integer rupiah.
     * Mengembalikan null bila bukan bilangan bulat rupiah.
     */
    public static function parse(mixed $input): ?int
    {
        if (is_int($input)) {
            return $input;
        }

        if (! is_string($input)) {
            return null;
        }

        $clean = str_replace(' ', '', preg_replace('/^\s*rp\.?\s*/i', '', trim($input)));

        // Titik hanya boleh sebagai pemisah ribuan (kelompok tepat 3 digit). "1500.50" ditolak,
        // bukan dibaca diam-diam sebagai Rp 150.050.
        if (preg_match('/^(\d{1,3}(\.\d{3})+|\d+)$/', $clean) !== 1) {
            return null;
        }

        $digits = str_replace('.', '', $clean);

        return strlen($digits) <= 12 ? (int) $digits : null;
    }

    /**
     * Format untuk tampilan (hanya dipakai di lapisan view).
     */
    public static function format(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
