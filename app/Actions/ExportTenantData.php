<?php

namespace App\Actions;

use App\Exports\TenantExportRegistry;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use BackedEnum;
use DateTimeInterface;
use RuntimeException;
use ZipArchive;

class ExportTenantData
{
    public function __construct(
        private TenantExportRegistry $registry,
        private AuditLogger $audit,
    ) {}

    /**
     * Membuat arsip ZIP berisi satu CSV per tabel, hanya untuk tenant aktif
     * (query model terkena TenantScope). Mengembalikan path berkas sementara.
     */
    public function handle(): string
    {
        $tenant = TenantContext::instance()->tenant()
            ?? throw new RuntimeException('Ekspor membutuhkan tenant aktif.');

        $zipPath = tempnam(sys_get_temp_dir(), 'kostra-ekspor-');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip ekspor.');
        }

        $csvFiles = [];

        foreach ($this->registry->all() as $model) {
            $csvPath = tempnam(sys_get_temp_dir(), 'kostra-csv-');
            $csvFiles[] = $csvPath;
            $handle = fopen($csvPath, 'w');

            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            $columns = $model::exportColumns();
            fputcsv($handle, $columns);

            // Query model memakai TenantScope sehingga hanya baris tenant ini yang terbaca.
            $model::query()->orderBy('id')->chunkById(500, function ($rows) use ($handle, $columns): void {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(fn (string $column) => $this->cell($row->getAttribute($column)), $columns));
                }
            });

            fclose($handle);
            $zip->addFile($csvPath, $model::exportName().'.csv');
        }

        $zip->close();

        foreach ($csvFiles as $file) {
            @unlink($file);
        }

        $this->audit->record('exported_data', $tenant, null, ['tables' => count($this->registry->all())]);

        return $zipPath;
    }

    private function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
