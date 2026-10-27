<?php

namespace App\Actions\Billing;

use App\Actions\Concerns\ResolvesTenant;
use App\Contracts\InvoicePaidTotal;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecalculateInvoice
{
    use ResolvesTenant;

    public function __construct(private readonly InvoicePaidTotal $paidTotal) {}

    /**
     * Menghitung ulang subtotal (dari item), adjustment_total (dari penyesuaian), total, paid_total
     * (dari alokasi pembayaran aktif), dan status. Dipakai ulang oleh penerbitan, penyesuaian,
     * dan pembatalan pembayaran. Tagihan batal tidak dihitung ulang.
     */
    public function __invoke(Invoice $invoice): Invoice
    {
        $tenant = $this->currentTenant();

        return DB::transaction(function () use ($invoice, $tenant) {
            $fresh = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($fresh->status === InvoiceStatus::Void) {
                return $fresh;
            }

            $subtotal = (int) InvoiceItem::query()->where('invoice_id', $fresh->getKey())->sum('amount');
            $adjustments = (int) InvoiceAdjustment::query()->where('invoice_id', $fresh->getKey())->sum('amount');
            $total = $subtotal + $adjustments;

            if ($total < 0) {
                throw new LogicException('Total tagihan tidak boleh negatif.');
            }

            $paid = $this->paidTotal->forInvoice($fresh);

            if ($paid > $total) {
                throw new LogicException('Jumlah terbayar tidak boleh melebihi total tagihan.');
            }

            $today = $tenant->today();

            $status = match (true) {
                $fresh->status === InvoiceStatus::Draft => InvoiceStatus::Draft,
                $total > 0 && $paid >= $total => InvoiceStatus::Paid,
                $fresh->due_date->toDateString() < $today->toDateString() => InvoiceStatus::Overdue,
                $paid > 0 => InvoiceStatus::Partial,
                default => InvoiceStatus::Issued,
            };

            $fresh->update([
                'subtotal' => $subtotal,
                'adjustment_total' => $adjustments,
                'total' => $total,
                'paid_total' => $paid,
                'status' => $status,
                'paid_at' => $status === InvoiceStatus::Paid ? ($fresh->paid_at ?? now()) : null,
            ]);

            return $fresh;
        });
    }
}
