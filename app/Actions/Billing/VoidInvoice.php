<?php

namespace App\Actions\Billing;

use App\Contracts\InvoicePaidTotal;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidInvoice
{
    public function __construct(private readonly InvoicePaidTotal $paidTotal) {}

    /**
     * Membatalkan tagihan (status void; baris tidak dihapus dan nomornya tetap). Tagihan lunas atau yang
     * sudah punya pembayaran aktif tidak boleh dibatalkan; batalkan pembayarannya lebih dulu.
     */
    public function __invoke(Invoice $invoice, string $reason): Invoice
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi (3 sampai 255 karakter).']);
        }

        return DB::transaction(function () use ($invoice, $reason) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages(['general' => 'Tagihan ini sudah dibatalkan.']);
            }

            if ($locked->status === InvoiceStatus::Paid) {
                throw ValidationException::withMessages(['general' => 'Tagihan lunas tidak bisa dibatalkan. Koreksi lewat penyesuaian.']);
            }

            if ($this->paidTotal->forInvoice($locked) > 0 || $locked->paid_total > 0) {
                throw ValidationException::withMessages(['general' => 'Tagihan ini sudah punya pembayaran. Batalkan pembayarannya lebih dulu.']);
            }

            $locked->update([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
                'paid_at' => null,
            ]);

            return $locked;
        });
    }
}
