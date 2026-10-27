<?php

namespace App\Services\Reports;

use App\Support\Rupiah;
use Carbon\CarbonImmutable;

/**
 * Bentuk tunggal sebuah laporan: layar, PDF, dan Excel semuanya membaca objek ini, jadi barisnya
 * selalu sama. Nilai uang disimpan sebagai integer rupiah; pemformatan hanya terjadi saat tampil.
 *
 * Tipe kolom: text, money, int, date (Y-m-d), percent (angka 0-100), decimal (satu desimal).
 */
final class ReportTable
{
    /**
     * @param  array<string, string>  $meta  label => nilai (properti, periode, tanggal cetak)
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals  kunci kolom => nilai; kolom pertama berisi label
     * @param  list<array{label: string, value: int|float, type: string}>  $summary
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $meta,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?array $totals = null,
        public readonly array $summary = [],
    ) {}

    /**
     * Teks tampilan satu sel (layar dan PDF).
     *
     * @param  array{key: string, label: string, type: string}  $column
     */
    public static function display(array $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::displayAs($column['type'], $value);
    }

    public static function displayAs(string $type, mixed $value): string
    {
        // Baris total mengisi kolom tanggal dengan label teks ("Total"): tampilkan apa adanya.
        if (in_array($type, ['date', 'month'], true) && preg_match('/^\d{4}-\d{2}/', (string) $value) !== 1) {
            return (string) $value;
        }

        return match ($type) {
            'money' => Rupiah::format((int) $value),
            'int' => number_format((int) $value, 0, ',', '.'),
            'percent' => number_format((float) $value, 1, ',', '.').'%',
            'decimal' => number_format((float) $value, 1, ',', '.'),
            'date' => CarbonImmutable::parse((string) $value)->locale('id')->translatedFormat('j M Y'),
            'month' => CarbonImmutable::parse((string) $value)->locale('id')->translatedFormat('F Y'),
            default => (string) $value,
        };
    }

    public function isRightAligned(string $type): bool
    {
        return in_array($type, ['money', 'int', 'percent', 'decimal'], true);
    }

    public function filename(string $extension): string
    {
        return $this->key.'-'.CarbonImmutable::now()->format('Ymd-His').'.'.$extension;
    }
}
