<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tunggakan = tagihan terbuka (belum lunas, tidak batal) yang jatuh temponya sudah lewat. Dihitung dari
 * due_date dan sisa tagihan, bukan dari status overdue, supaya tetap benar bila penjadwal terlambat jalan.
 * Umur = jumlah hari sejak jatuh tempo: 1-7, 8-30, dan lebih dari 30 hari.
 */
class ArrearsReport
{
    public const BUCKETS = ['1-7' => '1 sampai 7 hari', '8-30' => '8 sampai 30 hari', '31+' => 'Lebih dari 30 hari'];

    public static function bucket(int $days): string
    {
        return match (true) {
            $days <= 7 => '1-7',
            $days <= 30 => '8-30',
            default => '31+',
        };
    }

    public function total(CarbonImmutable $today, ?int $propertyId = null): int
    {
        return (int) $this->base($today, $propertyId)
            // CAST ke SIGNED: selisih BIGINT UNSIGNED yang negatif akan melempar galat di MySQL.
            ->selectRaw('COALESCE(SUM(CAST(total AS SIGNED) - CAST(paid_total AS SIGNED)), 0) as outstanding')
            ->value('outstanding');
    }

    public function table(CarbonImmutable $today, ?int $propertyId, string $sort, array $meta): ReportTable
    {
        $invoices = $this->base($today, $propertyId)
            ->with(['resident:id,name', 'property:id,name', 'lease.room:id,number'])
            ->get();

        $todayDate = new DateTimeImmutable($today->toDateString());

        $rows = $invoices->map(function (Invoice $invoice) use ($todayDate) {
            $days = (int) (new DateTimeImmutable($invoice->due_date->toDateString()))->diff($todayDate)->days;

            return [
                'number' => $invoice->number,
                'resident' => $invoice->resident?->name,
                'property' => $invoice->property?->name,
                'room' => $invoice->lease?->room?->number,
                'due_date' => $invoice->due_date->toDateString(),
                'days' => $days,
                'bucket' => self::bucket($days),
                'remaining' => $invoice->remaining(),
            ];
        });

        $rows = ($sort === 'age'
            ? $rows->sortBy([['days', 'desc'], ['remaining', 'desc']])
            : $rows->sortBy([['remaining', 'desc'], ['days', 'desc']]))->values();

        $summary = [];

        foreach (self::BUCKETS as $key => $label) {
            $summary[] = ['label' => 'Umur '.$label, 'value' => (int) $rows->where('bucket', $key)->sum('remaining'), 'type' => 'money'];
        }

        return new ReportTable(
            key: 'tunggakan',
            title: 'Laporan tunggakan',
            meta: $meta,
            columns: [
                ['key' => 'number', 'label' => 'Tagihan', 'type' => 'text'],
                ['key' => 'resident', 'label' => 'Penghuni', 'type' => 'text'],
                ['key' => 'property', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'room', 'label' => 'Kamar', 'type' => 'text'],
                ['key' => 'due_date', 'label' => 'Jatuh tempo', 'type' => 'date'],
                ['key' => 'days', 'label' => 'Terlambat (hari)', 'type' => 'int'],
                ['key' => 'bucket', 'label' => 'Umur', 'type' => 'text'],
                ['key' => 'remaining', 'label' => 'Sisa tagihan', 'type' => 'money'],
            ],
            rows: $rows->all(),
            totals: ['number' => 'Total tunggakan', 'remaining' => (int) $rows->sum('remaining')],
            summary: $summary,
        );
    }

    private function base(CarbonImmutable $today, ?int $propertyId): Builder
    {
        return Invoice::query()
            ->whereIn('status', InvoiceStatus::openValues())
            ->where('due_date', '<', $today->toDateString())
            ->whereColumn('total', '>', 'paid_total')
            ->when($propertyId, fn (Builder $query) => $query->where('property_id', $propertyId));
    }
}
