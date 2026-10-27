<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

class Dates
{
    /**
     * Membaca tanggal bisnis YYYY-MM-DD sebagai awal hari pada zona waktu tenant.
     * Mengembalikan null (bukan melempar) untuk nilai yang bukan tanggal valid, termasuk 2026-02-30.
     */
    public static function date(mixed $value, string $timezone): ?CarbonImmutable
    {
        return self::parse($value, '!Y-m-d', $timezone);
    }

    /**
     * Membaca periode YYYY-MM sebagai awal bulan pada zona waktu tenant.
     */
    public static function month(mixed $value, string $timezone): ?CarbonImmutable
    {
        return self::parse($value, '!Y-m', $timezone);
    }

    private static function parse(mixed $value, string $format, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);
        } catch (InvalidFormatException) {
            return null;
        }

        // Tanggal yang meluap (mis. 30 Februari) tidak dianggap valid.
        if ($parsed === false || $parsed->format(ltrim($format, '!')) !== $value) {
            return null;
        }

        return $parsed->startOfDay();
    }
}
