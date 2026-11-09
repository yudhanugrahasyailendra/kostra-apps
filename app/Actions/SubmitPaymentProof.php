<?php

namespace App\Actions;

use App\Enums\AttachmentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\ProofStatus;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPaymentProof
{
    public function __construct(private StoreAttachment $store) {}

    /**
     * Masukkan bukti transfer ke antrean verifikasi (status pending). Di Fase 6 diunggah oleh staf;
     * portal penghuni menyusul di Fase 10 dan memakai Action yang sama. Belum ada uang yang dicatat.
     */
    public function handle(Invoice $invoice, int $amount, ?string $paidOn, ?string $senderNote, UploadedFile $file, ?User $by = null, ?Resident $uploadedByResident = null): PaymentProof
    {
        if (! in_array($invoice->status, InvoiceStatus::open(), true)) {
            throw ValidationException::withMessages(['invoice' => 'Tagihan '.mb_strtolower($invoice->status->label()).' tidak dapat menerima bukti transfer.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari Rp 0.']);
        }

        if ($amount > $invoice->outstanding()) {
            throw ValidationException::withMessages(['amount' => 'Jumlah melebihi sisa tagihan.']);
        }

        return DB::transaction(function () use ($invoice, $amount, $paidOn, $senderNote, $file, $by, $uploadedByResident) {
            $proof = PaymentProof::create([
                'invoice_id' => $invoice->id,
                'resident_id' => $invoice->resident_id,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'sender_note' => $senderNote,
                'status' => ProofStatus::Pending,
            ]);

            $this->store->handle($proof, $file, AttachmentCategory::TransferProof, $by, $uploadedByResident);

            return $proof;
        });
    }
}
