<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Menulis ReportTable ke Excel (openspout) dan PDF (dompdf). Setiap berkas memuat nama kos, nama
 * properti, periode, dan tanggal cetak (KEU-06). Baris diambil dari ReportTable yang sama dengan layar.
 */
class ReportExporter
{
    /**
     * @param  array{business: string, property: string, period: string, printed_at: string}  $meta
     * @return string Jalur berkas sementara; pemanggil menghapusnya setelah dikirim.
     */
    public function xlsx(ReportTable $table, array $meta): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kostra-laporan-');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($this->sheetName($table->title));

        $bold = (new Style)->setFontBold();
        $money = (new Style)->setFormat('#,##0');
        $moneyBold = (new Style)->setFontBold()->setFormat('#,##0');

        $writer->addRow(Row::fromValues([$table->title], $bold));
        $writer->addRow(Row::fromValues(['Kos', $meta['business']]));
        $writer->addRow(Row::fromValues(['Properti', $meta['property']]));
        $writer->addRow(Row::fromValues(['Periode', $meta['period']]));
        $writer->addRow(Row::fromValues(['Tanggal cetak', $meta['printed_at']]));

        foreach ($table->summary as $item) {
            $writer->addRow(Row::fromValues([$item['label'], $item['value']]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(array_column($table->columns, 'label'), $bold));

        foreach ($table->rows as $row) {
            $writer->addRow($this->row($table, $row, $money));
        }

        if ($table->totals !== []) {
            $writer->addRow($this->row($table, $table->totals, $moneyBold, $bold));
        }

        $writer->close();

        return $path;
    }

    /** @param  array{business: string, property: string, period: string, printed_at: string}  $meta */
    public function pdf(ReportTable $table, array $meta): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.report', ['table' => $table, 'meta' => $meta])->setPaper('a4', 'landscape');
    }

    /**
     * Tanggal ditulis teks Y-m-d dan uang sebagai angka bulat (bukan teks berformat) agar bisa dijumlah di Excel.
     *
     * @param  array<string, scalar|null>  $values
     */
    private function row(ReportTable $table, array $values, Style $moneyStyle, ?Style $textStyle = null): Row
    {
        $cells = [];

        foreach ($table->columns as $column) {
            $value = $values[$column['key']] ?? null;

            $cells[] = match (true) {
                $value === null => Cell::fromValue('', $textStyle),
                $column['type'] === 'money' => Cell::fromValue((int) $value, $moneyStyle),
                in_array($column['type'], ['number', 'percent'], true) && is_numeric($value) => Cell::fromValue($value + 0, $textStyle),
                default => Cell::fromValue((string) $value, $textStyle),
            };
        }

        return new Row($cells);
    }

    private function sheetName(string $title): string
    {
        // Nama lembar Excel maksimal 31 karakter dan tidak boleh memuat karakter tertentu.
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $title), 0, 31);
    }
}
