<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalisasi nomor ke format internasional (+62...). Bawaan Indonesia.
     * Mengembalikan null bila bentuknya tidak masuk akal.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (! $hasPlus) {
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '62'.$digits;
            }
        }

        $normalized = '+'.$digits;

        return preg_match('/^\+[1-9]\d{7,14}$/', $normalized) === 1 ? $normalized : null;
    }
}
