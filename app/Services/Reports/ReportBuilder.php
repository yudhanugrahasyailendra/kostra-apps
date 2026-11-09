<?php

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use InvalidArgumentException;

/**
 * Menyusun setiap laporan menjadi ReportTable dari kelas query. Layar, Excel, dan PDF memakai
 * hasil yang sama, jadi ekspor selalu memuat baris yang sama dengan layar.
 */
class ReportBuilder
{
    public const TYPES = [
        'rekap' => 'Rekap bulanan',
        'pemasukan' => 'Pemasukan',
        'pengeluaran' => 'Pengeluaran',
        'tunggakan' => 'Tunggakan',
        'okupansi' => 'Okupansi',
    ];

    /** Jumlah bulan pada rekap bulanan (bulan yang dipilih ditambah lima bulan sebelumnya). */
    public const RECAP_MONTHS = 6;

    public function __construct(
        private IncomeReport $income,
        private ExpenseReport $expenses,
        private ArrearsReport $arrears,
        private OccupancyReport $occupancy,
        private MonthlyCashflowReport $cashflow,
    ) {}

    public function build(string $type, ReportFilters $filters): ReportTable
    {
        return match ($type) {
            'rekap' => $this->recap($filters),
            'pemasukan' => $this->income($filters),
            'pengeluaran' => $this->expenses($filters),
            'tunggakan' => $this->arrears($filters),
            'okupansi' => $this->occupancy($filters),
            default => throw new InvalidArgumentException("Jenis laporan tidak dikenal: $type"),
        };
    }

    /** Teks periode untuk judul berkas ekspor dan layar. */
    public function periodLabel(string $type, ReportFilters $filters): string
    {
        return match ($type) {
            'tunggakan', 'okupansi' => 'Per '.$filters->asOf->locale('id')->translatedFormat('j F Y'),
            'rekap' => $filters->period->label().' (dan '.(self::RECAP_MONTHS - 1).' bulan sebelumnya)',
            default => $filters->period->label(),
        };
    }

    private function recap(ReportFilters $f): ReportTable
    {
        $rows = collect($this->cashflow->months($f->period->key(), self::RECAP_MONTHS, $f->propertyId));
        $income = (int) $rows->sum('income');
        $expense = (int) $rows->sum('expense');

        return new ReportTable(
            'rekap',
            'Rekap bulanan pemasukan dan pengeluaran',
            [
                ['key' => 'label', 'label' => 'Bulan', 'type' => 'text'],
                ['key' => 'income', 'label' => 'Pemasukan', 'type' => 'money'],
                ['key' => 'expense', 'label' => 'Pengeluaran', 'type' => 'money'],
                ['key' => 'net', 'label' => 'Saldo bersih', 'type' => 'money'],
            ],
            $rows->map(fn (array $row) => [
                'month' => $row['month'], 'label' => $row['label'],
                'income' => $row['income'], 'expense' => $row['expense'], 'net' => $row['net'],
            ])->all(),
            ['label' => 'Total', 'income' => $income, 'expense' => $expense, 'net' => $income - $expense],
        );
    }

    private function income(ReportFilters $f): ReportTable
    {
        $rows = $this->income->rows($f->period, $f->propertyId)->map(fn (array $row) => [
            ...$row,
            'method_label' => PaymentMethod::tryFrom((string) $row['method'])?->label() ?? $row['method'],
        ]);

        return new ReportTable(
            'pemasukan',
            'Laporan pemasukan',
            [
                ['key' => 'paid_on', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'receipt_number', 'label' => 'Kuitansi', 'type' => 'text'],
                ['key' => 'resident_name', 'label' => 'Penghuni', 'type' => 'text'],
                ['key' => 'property_name', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'invoice_number', 'label' => 'Tagihan', 'type' => 'text'],
                ['key' => 'method_label', 'label' => 'Metode', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money'],
            ],
            $rows->map(fn (array $row) => collect($row)->except('method')->all())->all(),
            ['paid_on' => 'Total', 'amount' => (int) $rows->sum('amount')],
            [['label' => 'Jumlah pembayaran', 'value' => $this->income->count($f->period, $f->propertyId), 'type' => 'number']],
        );
    }

    private function expenses(ReportFilters $f): ReportTable
    {
        $rows = $this->expenses->rows($f->period, $f->propertyId, $f->categoryId);

        return new ReportTable(
            'pengeluaran',
            'Laporan pengeluaran',
            [
                ['key' => 'spent_on', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'category_name', 'label' => 'Kategori', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Keterangan', 'type' => 'text'],
                ['key' => 'vendor_name', 'label' => 'Penyedia', 'type' => 'text'],
                ['key' => 'property_name', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money'],
            ],
            $rows->all(),
            ['spent_on' => 'Total', 'amount' => (int) $rows->sum('amount')],
        );
    }

    private function arrears(ReportFilters $f): ReportTable
    {
        $rows = $this->arrears->rows($f->asOf, $f->propertyId);
        $summary = $this->arrears->summary($f->asOf, $f->propertyId);

        return new ReportTable(
            'tunggakan',
            'Laporan tunggakan',
            [
                ['key' => 'invoice_number', 'label' => 'Tagihan', 'type' => 'text'],
                ['key' => 'resident_name', 'label' => 'Penghuni', 'type' => 'text'],
                ['key' => 'property_name', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'room_number', 'label' => 'Kamar', 'type' => 'text'],
                ['key' => 'due_date', 'label' => 'Jatuh tempo', 'type' => 'date'],
                ['key' => 'days_late', 'label' => 'Terlambat (hari)', 'type' => 'number'],
                ['key' => 'bucket_label', 'label' => 'Umur', 'type' => 'text'],
                ['key' => 'outstanding', 'label' => 'Sisa tagihan', 'type' => 'money'],
            ],
            $rows->map(fn (array $row) => collect($row)->except(['invoice_id', 'bucket'])->all())->all(),
            ['invoice_number' => 'Total', 'outstanding' => $summary['total']],
            collect($summary['buckets'])->map(fn (array $b) => [
                'label' => $b['label'].' ('.$b['count'].' tagihan)', 'value' => $b['amount'], 'type' => 'money',
            ])->values()->all(),
        );
    }

    private function occupancy(ReportFilters $f): ReportTable
    {
        $data = $this->occupancy->at($f->asOf, $f->propertyId);

        return new ReportTable(
            'okupansi',
            'Laporan okupansi',
            [
                ['key' => 'name', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'total', 'label' => 'Kamar', 'type' => 'number'],
                ['key' => 'occupied', 'label' => 'Terisi', 'type' => 'number'],
                ['key' => 'vacant', 'label' => 'Kosong', 'type' => 'number'],
                ['key' => 'percent', 'label' => 'Okupansi', 'type' => 'percent'],
                ['key' => 'avg_vacant_days', 'label' => 'Rata-rata hari kosong', 'type' => 'number'],
            ],
            array_map(fn (array $p) => collect($p)->except('id')->all(), $data['properties']),
            [
                'name' => 'Semua properti', 'total' => $data['total'], 'occupied' => $data['occupied'], 'vacant' => $data['vacant'],
                'percent' => $data['percent'], 'avg_vacant_days' => $data['avg_vacant_days'],
            ],
            [['label' => 'Hari kosong dihitung sejak awal bulan', 'value' => $data['window_days'].' hari', 'type' => 'text']],
        );
    }
}
