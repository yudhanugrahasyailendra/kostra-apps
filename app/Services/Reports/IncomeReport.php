<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Pemasukan = alokasi dari pembayaran berstatus active yang paid_on-nya jatuh di dalam periode
 * (tanggal bisnis menurut zona waktu tenant, jadi dibandingkan sebagai tanggal polos). Pembayaran
 * voided tidak pernah dihitung. Dashboard, rekap bulanan, dan laporan ini semuanya memakai query dasar yang sama.
 */
class IncomeReport
{
    public function __construct(private readonly TenantContext $context) {}

    public function total(CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId = null): int
    {
        return (int) $this->base($from, $to, $propertyId)->sum('payment_allocations.amount');
    }

    /**
     * Pemasukan per tanggal bayar (Y-m-d => rupiah), dasar rekap bulanan.
     *
     * @return array<string, int>
     */
    public function byDay(CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId = null): array
    {
        return $this->base($from, $to, $propertyId)
            ->selectRaw('payments.paid_on as day, SUM(payment_allocations.amount) as total')
            ->groupBy('payments.paid_on')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day) => [substr((string) $day, 0, 10) => (int) $total])
            ->all();
    }

    public function table(CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId, array $meta): ReportTable
    {
        $groups = $this->base($from, $to, $propertyId)
            ->selectRaw('invoices.property_id as property_id, COUNT(DISTINCT payments.id) as payment_count, SUM(payment_allocations.amount) as total')
            ->groupBy('invoices.property_id')
            ->get();

        $names = Property::query()->withTrashed()->whereIn('id', $groups->pluck('property_id'))->pluck('name', 'id');

        $rows = $groups
            ->map(fn ($group) => [
                'property' => $names[$group->property_id] ?? 'Properti dihapus',
                'payments' => (int) $group->payment_count,
                'total' => (int) $group->total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        // Satu pembayaran bisa melunasi tagihan di dua properti: jumlah pembayaran total dihitung tersendiri.
        $distinctPayments = (int) $this->base($from, $to, $propertyId)->distinct()->count('payments.id');

        return new ReportTable(
            key: 'pemasukan',
            title: 'Laporan pemasukan',
            meta: $meta,
            columns: [
                ['key' => 'property', 'label' => 'Properti', 'type' => 'text'],
                ['key' => 'payments', 'label' => 'Jumlah pembayaran', 'type' => 'int'],
                ['key' => 'total', 'label' => 'Pemasukan', 'type' => 'money'],
            ],
            rows: $rows,
            totals: ['property' => 'Total', 'payments' => $distinctPayments, 'total' => array_sum(array_column($rows, 'total'))],
        );
    }

    /**
     * Join ke payments dan invoices ditulis dengan tenant_id eksplisit pada tiap tabel, karena
     * global scope hanya berlaku pada model dasar query ini.
     */
    private function base(CarbonImmutable $from, CarbonImmutable $to, ?int $propertyId): Builder
    {
        $tenantId = $this->context->id() ?? throw new LogicException('Laporan harus dijalankan dalam konteks tenant.');

        return PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('payments.tenant_id', $tenantId)
            ->where('invoices.tenant_id', $tenantId)
            ->where('payments.status', PaymentStatus::Active->value)
            ->whereBetween('payments.paid_on', [$from->toDateString(), $to->toDateString()])
            ->when($propertyId, fn (Builder $query) => $query->where('invoices.property_id', $propertyId));
    }
}
