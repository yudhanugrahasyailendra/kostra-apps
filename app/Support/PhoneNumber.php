<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Ubah nomor lokal Indonesia ke format internasional (+628...).
     * Mengembalikan null bila bentuknya tidak masuk akal.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (! preg_match('/^62\d{8,13}$/', $digits)) {
            return null;
        }

        return '+'.$digits;
    }
}
