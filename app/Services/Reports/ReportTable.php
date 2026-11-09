<?php

namespace App\Services\Reports;

/**
 * Bentuk tunggal sebuah laporan: dipakai halaman laporan, ekspor Excel, dan ekspor PDF, sehingga
 * baris di layar dan di berkas selalu sama.
 *
 * Tipe kolom: text, date (Y-m-d), money (integer rupiah), number, percent.
 */
final class ReportTable
{
    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  list<array<string, scalar|null>>  $rows
     * @param  array<string, scalar|null>  $totals  Baris total (kunci = kunci kolom).
     * @param  list<array{label: string, value: scalar|null, type: string}>  $summary  Ringkasan di atas tabel.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $totals = [],
        public readonly array $summary = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'totals' => $this->totals === [] ? null : $this->totals,
            'summary' => $this->summary,
        ];
    }
}
