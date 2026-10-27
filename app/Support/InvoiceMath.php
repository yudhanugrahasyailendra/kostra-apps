<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Hitungan tagihan dengan integer saja. Tidak ada float untuk uang.
 */
class InvoiceMath
{
    /**
     * Jumlah baris = kuantitas x harga satuan, dibulatkan setengah ke atas ke rupiah.
     * Kuantitas berupa teks dengan paling banyak 2 desimal ("1", "1.5", "2.25").
     */
    public static function lineAmount(string|int $quantity, int $unitPrice): int
    {
        $hundredths = self::quantityToHundredths($quantity);

        return intdiv($hundredths * $unitPrice + 50, 100);
    }

    public static function quantityToHundredths(string|int $quantity): int
    {
        $quantity = trim((string) $quantity);

        if (preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', $quantity, $m) !== 1) {
            throw new InvalidArgumentException('Kuantitas harus angka dengan paling banyak 2 desimal.');
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /**
     * Sewa pro rata = sewa x hari tagih / hari dalam bulan, dibulatkan setengah ke atas.
     */
    public static function prorate(int $rent, int $billableDays, int $daysInMonth): int
    {
        if ($billableDays >= $daysInMonth) {
            return $rent;
        }

        return intdiv(2 * $rent * $billableDays + $daysInMonth, 2 * $daysInMonth);
    }

    /**
     * Rentang yang ditagih dalam satu bulan. Batas awal hanya dipotong bila pro rata bulan pertama aktif,
     * batas akhir hanya bila pro rata bulan terakhir aktif; selain itu bulan penuh.
     * Mengembalikan null bila tidak ada hari yang bisa ditagih.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, days: int, days_in_month: int, prorated: bool}|null
     */
    public static function billableRange(
        CarbonImmutable $leaseStart,
        ?CarbonImmutable $leaseEnd,
        CarbonImmutable $monthStart,
        bool $prorateFirst,
        bool $prorateLast,
    ): ?array {
        $monthStart = $monthStart->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();

        $start = ($prorateFirst && $leaseStart->gt($monthStart)) ? $leaseStart->startOfDay() : $monthStart;
        $end = ($prorateLast && $leaseEnd !== null && $leaseEnd->lt($monthEnd)) ? $leaseEnd->startOfDay() : $monthEnd;

        if ($end->lt($start)) {
            return null;
        }

        $daysInMonth = $monthStart->daysInMonth;
        $days = (int) round($start->diffInDays($end)) + 1;

        return [
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'days_in_month' => $daysInMonth,
            'prorated' => $days < $daysInMonth,
        ];
    }
}
