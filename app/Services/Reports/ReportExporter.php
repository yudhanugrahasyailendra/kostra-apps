<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor Excel dan PDF membaca ReportTable yang sama dengan tampilan layar, sehingga barisnya identik.
 */
class ReportExporter
{
    public function xlsx(ReportTable $table): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'kostra-xlsx-');

        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Laporan');

            $bold = (new Style)->setFontBold();
            $money = (new Style)->setFormat('#,##0');
            $percent = (new Style)->setFormat('0.0"%"');
            $decimal = (new Style)->setFormat('0.0');

            $writer->addRow(Row::fromValues([$this->safe($table->title)], $bold));

            foreach ($table->meta as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $this->safe((string) $value)]));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(array_column($table->columns, 'label'), $bold));

            foreach ($table->rows as $row) {
                $writer->addRow($this->row($table, $row, $money, $percent, $decimal));
            }

            if ($table->totals !== null) {
                $writer->addRow($this->row($table, $table->totals, (clone $money)->setFontBold(), (clone $percent)->setFontBold(), (clone $decimal)->setFontBold(), $bold));
            }

            foreach ($table->summary as $item) {
                $style = match ($item['type']) {
                    'money' => $money,
                    'decimal' => $decimal,
                    default => null,
                };

                $writer->addRow(new Row([Cell::fromValue($item['label']), Cell::fromValue($item['value'], $style)]));
            }

            $writer->close();
        } catch (\Throwable $e) {
            // Berkas sementara berisi data keuangan: jangan tertinggal bila penulisan gagal.
            @unlink($path);

            throw $e;
        }

        return response()->download($path, $table->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(ReportTable $table): Response
    {
        return Pdf::loadView('owner.reports.pdf', ['table' => $table])
            ->setPaper('a4', 'landscape')
            ->download($table->filename('pdf'));
    }

    /**
     * Angka ditulis sebagai angka (bisa dijumlah di Excel); teks dan tanggal ditulis seperti di layar.
     *
     * @param  array<string, mixed>  $values
     */
    private function row(ReportTable $table, array $values, Style $money, Style $percent, Style $decimal, ?Style $textStyle = null): Row
    {
        $cells = [];

        foreach ($table->columns as $column) {
            $value = $values[$column['key']] ?? null;

            $cells[] = match (true) {
                $value === null => Cell::fromValue('', $textStyle),
                $column['type'] === 'money' => Cell::fromValue((int) $value, $money),
                $column['type'] === 'int' => Cell::fromValue((int) $value, $textStyle),
                $column['type'] === 'percent' => Cell::fromValue((float) $value, $percent),
                $column['type'] === 'decimal' => Cell::fromValue((float) $value, $decimal),
                in_array($column['type'], ['date', 'month'], true) => Cell::fromValue($this->safe(ReportTable::display($column, $value)), $textStyle),
                default => Cell::fromValue($this->safe((string) $value), $textStyle),
            };
        }

        return new Row($cells);
    }

    /**
     * Teks yang diawali karakter rumus (= + - @) diberi awalan apostrof supaya tidak dibaca sebagai rumus
     * bila berkas dibuka atau diimpor ulang di aplikasi lain.
     */
    private function safe(string $text): string
    {
        return $text !== '' && str_contains('=+-@', $text[0]) ? "'".$text : $text;
    }
}
