<?php

namespace App\Actions\Payments;

use App\Actions\Billing\RecalculateInvoice;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidPayment
{
    public function __construct(private readonly RecalculateInvoice $recalculate) {}

    /**
     * Membatalkan pembayaran (alasan wajib). Pembayaran dan alokasinya tidak dihapus: statusnya menjadi
     * voided, alokasinya tidak lagi dihitung, lalu tagihan terkait dihitung ulang (kembali belum dibayar,
     * sebagian, atau terlambat sesuai jatuh tempo). Nomor kuitansinya tetap tercatat.
     */
    public function __invoke(Payment $payment, string $reason, User $actor): Payment
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi (3 sampai 255 karakter).']);
        }

        return DB::transaction(function () use ($payment, $reason, $actor) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status === PaymentStatus::Voided) {
                throw ValidationException::withMessages(['general' => 'Pembayaran ini sudah dibatalkan.']);
            }

            $locked->update([
                'status' => PaymentStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $actor->getKey(),
            ]);

            $invoiceIds = PaymentAllocation::query()->where('payment_id', $locked->getKey())->orderBy('invoice_id')->pluck('invoice_id');

            foreach (Invoice::query()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get() as $invoice) {
                ($this->recalculate)($invoice);
            }

            return $locked;
        });
    }
}
