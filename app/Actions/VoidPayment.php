<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidPayment
{
    public function __construct(private RecalculateInvoice $recalculate) {}

    /**
     * Batalkan pembayaran dengan alasan. Baris pembayaran dan alokasinya tetap tersimpan sebagai
     * riwayat; paid_total dan status tagihan terkait dihitung ulang (termasuk kembali menjadi
     * overdue bila sudah lewat jatuh tempo). Perubahan tercatat otomatis di audit_logs.
     */
    public function handle(Payment $payment, string $reason, User $by): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        return DB::transaction(function () use ($payment, $reason, $by) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::Voided) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah dibatalkan.']);
            }

            // Kunci tagihan terkait dalam urutan id yang sama dengan RecordPayment.
            $invoiceIds = $payment->allocations()->orderBy('invoice_id')->pluck('invoice_id');
            $invoices = Invoice::query()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get();

            $payment->forceFill([
                'status' => PaymentStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $by->id,
            ])->save();

            foreach ($invoices as $invoice) {
                $this->recalculate->handle($invoice);
            }

            return $payment;
        });
    }
}
