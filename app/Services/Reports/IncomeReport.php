<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Models\PaymentAllocation;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pemasukan = uang yang diterima (basis kas): alokasi pembayaran berstatus active yang `paid_on`-nya
 * jatuh di periode, dikelompokkan ke properti lewat tagihan yang dibayar. Pembayaran yang dibatalkan
 * tidak pernah masuk. Dashboard dan semua laporan membaca angka dari kelas ini.
 */
class IncomeReport
{
    /** @return Builder<PaymentAllocation> */
    private function base(ReportPeriod $period, ?int $propertyId): Builder
    {
        // Tabel yang di-join tidak membawa global scope tenant sendiri, jadi tenant_id dipasang eksplisit.
        $tenantId = TenantContext::instance()->id();

        return PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('payments.tenant_id', $tenantId)
            ->where('invoices.tenant_id', $tenantId)
            ->where('payments.status', PaymentStatus::Active->value)
            ->whereBetween('payments.paid_on', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($propertyId, fn (Builder $q) => $q->where('invoices.property_id', $propertyId));
    }

    public function total(ReportPeriod $period, ?int $propertyId = null): int
    {
        return (int) $this->base($period, $propertyId)->sum('payment_allocations.amount');
    }

    public function count(ReportPeriod $period, ?int $propertyId = null): int
    {
        return (int) $this->base($period, $propertyId)->distinct()->count('payments.id');
    }

    /**
     * Satu baris per alokasi (satu pembayaran yang menutup tiga tagihan menjadi tiga baris).
     *
     * @return Collection<int, array<string, scalar|null>>
     */
    public function rows(ReportPeriod $period, ?int $propertyId = null): Collection
    {
        return $this->base($period, $propertyId)
            ->join('residents', 'residents.id', '=', 'payments.resident_id')
            ->join('properties', 'properties.id', '=', 'invoices.property_id')
            ->where('residents.tenant_id', TenantContext::instance()->id())
            ->where('properties.tenant_id', TenantContext::instance()->id())
            ->orderBy('payments.paid_on')->orderBy('payments.id')->orderBy('payment_allocations.id')
            ->get([
                'payments.paid_on', 'payments.receipt_number', 'payments.method', 'residents.name as resident_name',
                'properties.name as property_name', 'invoices.number as invoice_number', 'payment_allocations.amount',
            ])
            ->map(fn ($row) => [
                'paid_on' => substr((string) $row->getRawOriginal('paid_on'), 0, 10),
                'receipt_number' => $row->receipt_number,
                'resident_name' => $row->resident_name,
                'property_name' => $row->property_name,
                'invoice_number' => $row->invoice_number,
                'method' => $row->getRawOriginal('method'),
                'amount' => (int) $row->amount,
            ]);
    }

    /**
     * Total per hari untuk rekap bulanan.
     *
     * @return array<string, int> Kunci Y-m-d.
     */
    public function dailyTotals(ReportPeriod $period, ?int $propertyId = null): array
    {
        return $this->base($period, $propertyId)
            ->groupBy('payments.paid_on')
            ->selectRaw('payments.paid_on as day, SUM(payment_allocations.amount) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [substr((string) $row->getRawOriginal('day'), 0, 10) => (int) $row->total])
            ->all();
    }
}
