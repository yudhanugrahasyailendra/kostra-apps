<?php

namespace App\Services\Export;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor seluruh data operasional tenant aktif ke satu berkas Excel (satu lembar per jenis data). Semua query
 * memakai model ber-scope tenant, jadi hanya data tenant yang sedang aktif yang ikut. Tidak memuat berkas lampiran,
 * foto KTP, token, atau kata sandi. Angka uang berupa rupiah bulat; tanggal ditulis YYYY-MM-DD.
 *
 * Tersedia untuk pemilik selama tenant belum ditutup, termasuk saat ditangguhkan atau langganan berakhir.
 */
class TenantDataExporter
{
    /**
     * @return array<string, array{headers: list<string>, rows: LazyCollection<int, list<mixed>>}>
     */
    public function sheets(): array
    {
        return [
            'Properti' => [
                'headers' => ['Nama', 'Alamat', 'Kota', 'Jumlah lantai', 'Aktif'],
                'rows' => Property::query()->lazyById(500)->map(fn (Property $p) => [$p->name, $p->address, $p->city, $p->floors, $p->is_active ? 'Ya' : 'Tidak']),
            ],
            'Kamar' => [
                'headers' => ['Properti', 'Nomor', 'Lantai', 'Harga', 'Status'],
                'rows' => Room::query()->with('property:id,name')->lazyById(500)->map(fn (Room $r) => [$r->property?->name, $r->number, $r->floor, $r->price, $r->status->value]),
            ],
            'Penghuni' => [
                'headers' => ['Nama', 'Nomor WhatsApp', 'Email', 'Pekerjaan', 'Aktif', 'Berhenti menerima pesan sejak'],
                'rows' => Resident::query()->lazyById(500)->map(fn (Resident $r) => [$r->name, $r->phone, $r->email, $r->occupation, $r->is_active ? 'Ya' : 'Tidak', $r->whatsapp_opt_out_at?->toDateString()]),
            ],
            'Kontrak' => [
                'headers' => ['Properti', 'Kamar', 'Penghuni utama', 'Status', 'Mulai', 'Berakhir', 'Sewa', 'Check-out'],
                'rows' => Lease::query()->with(['property:id,name', 'room:id,number', 'primaryResident:id,name'])->lazyById(500)->map(fn (Lease $l) => [
                    $l->property?->name, $l->room?->number, $l->primaryResident?->name, $l->status->value,
                    $l->start_date?->toDateString(), $l->end_date?->toDateString(), $l->rent_amount, $l->checked_out_at?->toDateString(),
                ]),
            ],
            'Tagihan' => [
                'headers' => ['Nomor', 'Penghuni', 'Properti', 'Periode', 'Jatuh tempo', 'Status', 'Subtotal', 'Penyesuaian', 'Total', 'Terbayar'],
                'rows' => Invoice::query()->with(['resident:id,name', 'property:id,name'])->lazyById(500)->map(fn (Invoice $i) => [
                    $i->number, $i->resident?->name, $i->property?->name, $i->period, $i->due_date?->toDateString(), $i->status->value,
                    $i->subtotal, $i->adjustment_total, $i->total, $i->paid_total,
                ]),
            ],
            'Pembayaran' => [
                'headers' => ['Kuitansi', 'Penghuni', 'Tanggal bayar', 'Metode', 'Jumlah', 'Status', 'Referensi'],
                'rows' => Payment::query()->with('resident:id,name')->lazyById(500)->map(fn (Payment $p) => [
                    $p->receipt_number, $p->resident?->name, $p->paid_on?->toDateString(), $p->method->value, $p->amount, $p->status->value, $p->reference,
                ]),
            ],
            'Pengeluaran' => [
                'headers' => ['Tanggal', 'Kategori', 'Properti', 'Jumlah', 'Keterangan', 'Penerima'],
                'rows' => Expense::query()->with(['category:id,name', 'property:id,name'])->lazyById(500)->map(fn (Expense $e) => [
                    $e->spent_on?->toDateString(), $e->category?->name, $e->property?->name ?? 'Umum', $e->amount, $e->description, $e->vendor_name,
                ]),
            ],
        ];
    }

    /**
     * Jumlah baris per lembar (tanpa judul), untuk ringkasan dan tes.
     *
     * @return Collection<string, int>
     */
    public function counts(): Collection
    {
        return collect([
            'Properti' => Property::query()->count(),
            'Kamar' => Room::query()->count(),
            'Penghuni' => Resident::query()->count(),
            'Kontrak' => Lease::query()->count(),
            'Tagihan' => Invoice::query()->count(),
            'Pembayaran' => Payment::query()->count(),
            'Pengeluaran' => Expense::query()->count(),
        ]);
    }

    public function xlsx(string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'kostra-export-');

        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $bold = (new Style)->setFontBold();
            $first = true;

            foreach ($this->sheets() as $name => $sheet) {
                $first ? $writer->getCurrentSheet()->setName($name) : $writer->addNewSheetAndMakeItCurrent()->setName($name);
                $first = false;

                $writer->addRow(Row::fromValues($sheet['headers'], $bold));

                foreach ($sheet['rows'] as $row) {
                    $writer->addRow(Row::fromValues(array_map(fn ($value) => $this->safe($value), $row)));
                }
            }

            $writer->close();
        } catch (\Throwable $e) {
            // Berkas sementara berisi data pribadi: jangan tertinggal bila penulisan gagal.
            @unlink($path);

            throw $e;
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Teks yang diawali karakter rumus (= + - @) diberi apostrof supaya tidak dibaca sebagai rumus saat dibuka di Excel.
     */
    private function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || ! str_contains('=+-@', $value[0])) {
            return $value;
        }

        // Nomor telepon internasional (+62...) bukan rumus: dibiarkan apa adanya.
        return preg_match('/^\+\d[\d\s-]*$/', $value) === 1 ? $value : "'".$value;
    }
}
