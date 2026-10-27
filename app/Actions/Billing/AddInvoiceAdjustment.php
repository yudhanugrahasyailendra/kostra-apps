<?php

namespace App\Actions\Billing;

use App\Contracts\InvoicePaidTotal;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddInvoiceAdjustment
{
    public const MAX_AMOUNT = 1_000_000_000;

    public function __construct(
        private readonly RecalculateInvoice $recalculate,
        private readonly InvoicePaidTotal $paidTotal,
    ) {}

    /**
     * Koreksi tagihan terbit lewat penyesuaian (jumlah item asli tidak diubah). Alasan wajib.
     * Total hasil penyesuaian harus sekurangnya Rp 1 dan tidak boleh di bawah yang sudah terbayar;
     * untuk membatalkan seluruhnya pakai VoidInvoice.
     *
     * @param  int  $amount  Positif menambah, negatif mengurangi
     */
    public function __invoke(Invoice $invoice, int $amount, string $reason, User $actor): InvoiceAdjustment
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan penyesuaian wajib diisi (3 sampai 255 karakter).']);
        }

        if ($amount === 0 || abs($amount) > self::MAX_AMOUNT) {
            throw ValidationException::withMessages(['amount' => 'Nominal penyesuaian harus lebih dari 0 dan wajar.']);
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $actor) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (in_array($locked->status, [InvoiceStatus::Void, InvoiceStatus::Draft], true)) {
                throw ValidationException::withMessages(['general' => 'Tagihan batal tidak bisa disesuaikan.']);
            }

            $newTotal = $locked->total + $amount;
            $paid = $this->paidTotal->forInvoice($locked);

            if ($newTotal < 1) {
                throw ValidationException::withMessages(['amount' => 'Total tagihan tidak boleh menjadi Rp 0 atau negatif. Batalkan tagihan bila memang tidak jadi ditagih.']);
            }

            if ($newTotal < $paid) {
                throw ValidationException::withMessages(['amount' => 'Total tagihan tidak boleh lebih kecil dari yang sudah terbayar.']);
            }

            $adjustment = InvoiceAdjustment::create([
                'invoice_id' => $locked->getKey(),
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => $actor->getKey(),
            ]);

            ($this->recalculate)($locked);

            return $adjustment;
        });
    }
}
