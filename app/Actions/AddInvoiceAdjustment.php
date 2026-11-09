<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddInvoiceAdjustment
{
    public function __construct(private RecalculateInvoice $recalculate) {}

    /**
     * Koreksi tagihan terbit: positif menambah, negatif mengurangi. Item asli tidak diubah. Alasan wajib,
     * dan pencatatan masuk audit lewat model InvoiceAdjustment. Total tidak boleh di bawah Rp 0
     * maupun di bawah jumlah yang sudah dibayar.
     */
    public function handle(Invoice $invoice, int $amount, string $reason, User $by): InvoiceAdjustment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan penyesuaian wajib diisi.']);
        }

        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah penyesuaian tidak boleh nol.']);
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $by) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (in_array($invoice->status, [InvoiceStatus::Void, InvoiceStatus::Draft], true)) {
                throw ValidationException::withMessages(['invoice' => 'Tagihan '.mb_strtolower($invoice->status->label()).' tidak dapat disesuaikan.']);
            }

            $newTotal = $invoice->total + $amount;

            if ($newTotal < 0) {
                throw ValidationException::withMessages(['amount' => 'Penyesuaian membuat total tagihan kurang dari Rp 0.']);
            }

            if ($newTotal < $invoice->paid_total) {
                throw ValidationException::withMessages(['amount' => 'Penyesuaian membuat total tagihan lebih kecil dari yang sudah dibayar.']);
            }

            $adjustment = InvoiceAdjustment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => $by->id,
            ]);

            $this->recalculate->handle($invoice);

            return $adjustment;
        });
    }
}
