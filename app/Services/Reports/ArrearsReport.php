<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tunggakan = tagihan terbuka (terbit, sebagian, lewat tempo) yang sudah lewat jatuh tempo dan masih
 * bersisa. Umur dihitung dari jatuh tempo ke tanggal acuan: 1-7, 8-30, dan lebih dari 30 hari.
 * Status `overdue` tidak dipakai sebagai syarat, jadi hasilnya tidak bergantung pada penjadwal.
 */
class ArrearsReport
{
    public const BUCKETS = [
        'd1_7' => '1-7 hari',
        'd8_30' => '8-30 hari',
        'd31_plus' => 'Lebih dari 30 hari',
    ];

    public static function bucketFor(int $daysLate): string
    {
        return match (true) {
            $daysLate <= 7 => 'd1_7',
            $daysLate <= 30 => 'd8_30',
            default => 'd31_plus',
        };
    }

    /** @return Collection<int, array<string, scalar|null>> Diurutkan dari sisa terbesar. */
    public function rows(CarbonImmutable $asOf, ?int $propertyId = null): Collection
    {
        return Invoice::query()
            ->open()
            ->whereColumn('total', '>', 'paid_total')
            ->where('due_date', '<', $asOf->toDateString())
            ->when($propertyId, fn (Builder $q) => $q->where('property_id', $propertyId))
            ->with(['resident', 'property', 'lease.room'])
            ->get()
            ->map(function (Invoice $invoice) use ($asOf) {
                $daysLate = (int) $invoice->due_date->diffInDays($asOf);
                $bucket = self::bucketFor($daysLate);

                return [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'resident_name' => $invoice->resident->name,
                    'property_name' => $invoice->property->name,
                    'room_number' => $invoice->lease?->room?->number,
                    'due_date' => $invoice->due_date->toDateString(),
                    'days_late' => $daysLate,
                    'bucket' => $bucket,
                    'bucket_label' => self::BUCKETS[$bucket],
                    'outstanding' => $invoice->outstanding(),
                ];
            })
            ->sortBy([['outstanding', 'desc'], ['days_late', 'desc'], ['invoice_number', 'asc']])
            ->values();
    }

    /**
     * @return array{total: int, count: int, buckets: array<string, array{label: string, count: int, amount: int}>}
     */
    public function summary(CarbonImmutable $asOf, ?int $propertyId = null): array
    {
        $rows = $this->rows($asOf, $propertyId);
        $buckets = [];

        foreach (self::BUCKETS as $key => $label) {
            $inBucket = $rows->where('bucket', $key);
            $buckets[$key] = ['label' => $label, 'count' => $inBucket->count(), 'amount' => (int) $inBucket->sum('outstanding')];
        }

        return ['total' => (int) $rows->sum('outstanding'), 'count' => $rows->count(), 'buckets' => $buckets];
    }
}
