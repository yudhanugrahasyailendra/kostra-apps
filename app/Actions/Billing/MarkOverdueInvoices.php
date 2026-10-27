<?php

namespace App\Actions\Billing;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class MarkOverdueInvoices
{
    use ResolvesTenant;

    /**
     * Tagihan terbit atau sebagian yang lewat jatuh tempo menjadi terlambat. Tagihan lunas dan batal
     * tidak berubah. Pembaruan massal (tanpa jejak per baris) karena ini perubahan status otomatis.
     *
     * @return int Jumlah tagihan yang diubah
     */
    public function __invoke(): int
    {
        $today = $this->currentTenant()->today();

        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Partial->value])
            ->where('due_date', '<', $today->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()]);
    }
}
