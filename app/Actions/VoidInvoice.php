<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidInvoice
{
    /**
     * Batalkan tagihan dengan alasan. Baris tidak dihapus. Tagihan lunas atau yang sudah punya
     * pembayaran tidak boleh dibatalkan; koreksi lewat penyesuaian (atau batalkan pembayarannya dulu).
     */
    public function handle(Invoice $invoice, string $reason): Invoice
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        return DB::transaction(function () use ($invoice, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages(['invoice' => 'Tagihan ini sudah dibatalkan.']);
            }

            if ($invoice->status === InvoiceStatus::Paid || $invoice->paid_total > 0) {
                throw ValidationException::withMessages(['invoice' => 'Tagihan yang sudah dibayar tidak dapat dibatalkan. Koreksi lewat penyesuaian.']);
            }

            $invoice->forceFill([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            return $invoice;
        });
    }
}
