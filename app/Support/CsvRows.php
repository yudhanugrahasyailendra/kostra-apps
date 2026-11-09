<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Pembaca CSV untuk impor: pemisah koma atau titik koma, BOM dibuang, nama kolom
 * dipetakan ke nama baku. Kunci array hasil = nomor baris di berkas (header = baris 1).
 */
final class CsvRows
{
    /**
     * @param  array<string, string>  $headers  nama kolom di berkas (huruf kecil) => nama baku
     * @param  array<string, string>  $required  nama baku => nama kolom yang ditampilkan di pesan galat
     * @return array<int, array<string, string>>
     */
    public static function read(string $path, array $headers, array $required): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca.']);
        }

        try {
            $first = (string) fgets($handle);
            rewind($handle);
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

            $header = fgetcsv($handle, 0, $delimiter);

            if ($header === false) {
                throw ValidationException::withMessages(['file' => 'Berkas kosong.']);
            }

            $columns = array_map(
                fn ($h) => $headers[mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h) ?? ''))] ?? null,
                $header,
            );

            foreach ($required as $name => $label) {
                if (! in_array($name, $columns, true)) {
                    throw ValidationException::withMessages(['file' => "Kolom \"{$label}\" tidak ditemukan. Gunakan templat CSV."]);
                }
            }

            $rows = [];
            $line = 1;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;

                if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $row = [];

                foreach ($columns as $index => $name) {
                    if ($name !== null) {
                        $row[$name] = (string) ($data[$index] ?? '');
                    }
                }

                $rows[$line] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
