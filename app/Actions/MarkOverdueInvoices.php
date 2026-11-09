<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;

class MarkOverdueInvoices
{
    /**
     * Ubah tagihan terbit atau sebagian dibayar yang lewat jatuh tempo menjadi overdue, untuk satu tenant.
     * Tagihan lunas, batal, dan draf tidak disentuh. Mengembalikan jumlah tagihan yang berubah.
     */
    public function handle(Tenant $tenant): int
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant) {
            return Invoice::query()
                ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Partial->value])
                ->whereDate('due_date', '<', BillingClock::today($tenant)->toDateString())
                ->update(['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()]);
        });
    }
}
