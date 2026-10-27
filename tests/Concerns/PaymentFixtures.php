<?php

namespace Tests\Concerns;

use App\Actions\Payments\RecordPayment;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;

trait PaymentFixtures
{
    use BillingFixtures;

    /**
     * Tagihan bulan berjalan (terbit lewat penjadwal) untuk satu kontrak baru.
     *
     * @param  array<string, mixed>  $leaseExtra
     */
    protected function openInvoice(array $leaseExtra = [], ?string $period = null, ?Lease $lease = null): Invoice
    {
        $lease ??= $this->leaseFor($leaseExtra);
        $this->generate($period);

        return $this->asTenant($this->tenant, fn () => Invoice::query()
            ->where('lease_id', $lease->getKey())
            ->when($period, fn ($q) => $q->where('period', $period))
            ->latest('id')->firstOrFail());
    }

    /**
     * Mencatat pembayaran lewat Action (jalur yang sama dengan UI).
     *
     * @param  list<array{0: Invoice, 1: int}>  $allocations
     * @param  array<string, mixed>  $overrides
     */
    protected function record(array $allocations, array $overrides = [], ?User $actor = null, ?Tenant $tenant = null): Payment
    {
        $tenant ??= $this->tenant;

        return $this->asTenant($tenant, fn () => app(RecordPayment::class)(array_merge([
            'resident_id' => $allocations[0][0]->resident_id,
            'paid_on' => $tenant->today()->toDateString(),
            'method' => 'cash',
            'allocations' => array_map(fn (array $a) => ['invoice_id' => $a[0]->getKey(), 'amount' => $a[1]], $allocations),
        ], $overrides), $actor ?? $this->owner));
    }

    protected function fresh(Invoice $invoice): Invoice
    {
        return $this->asTenant($this->tenant, fn () => Invoice::findOrFail($invoice->getKey()));
    }
}
