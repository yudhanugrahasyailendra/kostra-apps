<?php

namespace App\Exports;

/**
 * Model milik tenant yang boleh diekspor (NFR-12). Kolom rahasia
 * (password, token_hash, kunci) tidak boleh ada di exportColumns().
 * Setiap fase mendaftarkan model barunya di TenantExportRegistry.
 */
interface TenantExportable
{
    /** Nama berkas CSV tanpa ekstensi, mis. "pengguna". */
    public static function exportName(): string;

    /** @return list<string> */
    public static function exportColumns(): array;
}
