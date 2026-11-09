<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\InvoiceItem;
use App\Services\Billing\BillingClock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat angka tagihan dihitung: subtotal dari item, adjustment_total dari penyesuaian,
 * total = subtotal + adjustment_total, paid_total dari alokasi pembayaran aktif, lalu status.
 * Tagihan batal tidak berubah.
 */
class RecalculateInvoice
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === InvoiceStatus::Void) {
                return $invoice;
            }

            $subtotal = (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->sum('amount');
            $adjustments = (int) InvoiceAdjustment::query()->where('invoice_id', $invoice->id)->sum('amount');
            $total = $subtotal + $adjustments;

            if ($total < 0) {
                throw ValidationException::withMessages(['amount' => 'Total tagihan tidak boleh kurang dari Rp 0.']);
            }

            $paid = $this->paidTotal($invoice);
            $status = self::statusFor($invoice->status, $total, $paid, $invoice->due_date, BillingClock::today());

            $invoice->forceFill([
                'subtotal' => $subtotal,
                'adjustment_total' => $adjustments,
                'total' => $total,
                'paid_total' => $paid,
                'status' => $status,
                'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null,
            ])->save();

            return $invoice;
        });
    }

    /**
     * Status dari angka dan tanggal. Draf dan batal tidak berubah; selain itu: lunas bila terbayar
     * penuh, lewat jatuh tempo bila belum lunas dan tanggalnya lewat, sebagian bila ada yang terbayar.
     */
    public static function statusFor(InvoiceStatus $current, int $total, int $paid, CarbonInterface $dueDate, CarbonInterface $today): InvoiceStatus
    {
        if (in_array($current, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
            return $current;
        }

        return match (true) {
            $paid >= $total => InvoiceStatus::Paid,
            $dueDate->toDateString() < $today->toDateString() => InvoiceStatus::Overdue,
            $paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Issued,
        };
    }

    /**
     * Jumlah alokasi dari pembayaran berstatus active. Tabel pembayaran baru ada di Fase 6, jadi
     * sampai saat itu tidak ada uang yang teralokasi dan hasilnya 0. Fase 6 mengganti isi method ini.
     */
    private function paidTotal(Invoice $invoice): int
    {
        return 0;
    }
}
