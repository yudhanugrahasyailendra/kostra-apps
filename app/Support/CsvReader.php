<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class CsvReader
{
    /**
     * Membaca CSV (koma atau titik koma, BOM, UTF-8 atau Windows-1252) menjadi baris bernama kolom baku.
     * Kolom yang tidak dikenal dibuang. Kesalahan berkas dilaporkan pada kunci "file".
     *
     * @param  array<string, string>  $aliases  nama kolom huruf kecil => nama kolom baku
     * @param  string  $required  kolom baku yang wajib ada di header
     * @return list<array{line: int, data: array<string, string>}>
     */
    public static function read(string $path, array $aliases, string $required, int $maxRows): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak bisa dibaca.']);
        }

        try {
            $first = fgets($handle);

            if ($first === false || trim($first) === '') {
                throw ValidationException::withMessages(['file' => 'Berkas kosong.']);
            }

            $first = self::toUtf8(preg_replace('/^\xEF\xBB\xBF/', '', $first));
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

            $header = array_map(
                fn ($name) => $aliases[mb_strtolower(trim((string) $name))] ?? null,
                str_getcsv($first, $delimiter, '"', ''),
            );

            if (! in_array($required, $header, true)) {
                throw ValidationException::withMessages(['file' => 'Kolom wajib tidak ditemukan di baris pertama.']);
            }

            $rows = [];
            $line = 1;

            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;

                if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                    continue;
                }

                if (count($rows) >= $maxRows) {
                    throw ValidationException::withMessages(['file' => "Maksimal {$maxRows} baris per impor."]);
                }

                $data = [];

                foreach ($header as $index => $column) {
                    if ($column !== null) {
                        $data[$column] = self::toUtf8((string) ($cells[$index] ?? ''));
                    }
                }

                $rows[] = ['line' => $line, 'data' => $data];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private static function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
