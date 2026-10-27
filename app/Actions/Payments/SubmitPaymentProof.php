<?php

namespace App\Actions\Payments;

use App\Actions\Attachments\StoreAttachment;
use App\Actions\Concerns\ResolvesTenant;
use App\Contracts\InvoicePaidTotal;
use App\Enums\AttachmentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\ProofStatus;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\Dates;
use App\Support\Rupiah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPaymentProof
{
    use ResolvesTenant;

    public function __construct(
        private readonly StoreAttachment $store,
        private readonly InvoicePaidTotal $paidTotal,
    ) {}

    /**
     * Mencatat bukti transfer (menunggu verifikasi). Pada Fase 6 dicatat oleh staf atas nama penghuni;
     * unggahan oleh penghuni lewat portal menyusul di Fase 10. Tidak mengubah tagihan sebelum disetujui.
     *
     * @param  array<string, mixed>  $data  amount, [paid_on, sender_note]
     */
    public function __invoke(Invoice $invoice, array $data, UploadedFile $file, ?User $actor = null): PaymentProof
    {
        $tenant = $this->currentTenant();
        $today = $tenant->today();

        $amount = $data['amount'] ?? null;

        if (! is_int($amount) || $amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus rupiah bulat lebih dari 0.']);
        }

        $paidOn = null;

        if (isset($data['paid_on']) && $data['paid_on'] !== '') {
            $paidOn = Dates::date($data['paid_on'], $tenant->timezone);

            if ($paidOn === null || $paidOn->gt($today) || $paidOn->lt($today->subYear())) {
                throw ValidationException::withMessages(['paid_on' => 'Tanggal transfer tidak valid (tidak boleh di masa depan atau lebih dari 1 tahun lalu).']);
            }
        }

        return DB::transaction(function () use ($invoice, $amount, $paidOn, $data, $file, $actor) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! in_array($locked->status, [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue], true)) {
                throw ValidationException::withMessages(['general' => 'Tagihan ini tidak menerima pembayaran lagi.']);
            }

            $remaining = $locked->total - $this->paidTotal->forInvoice($locked);

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Nominal melebihi sisa tagihan ('.Rupiah::format(max(0, $remaining)).').']);
            }

            $note = is_string($data['sender_note'] ?? null) ? trim($data['sender_note']) : '';

            $proof = PaymentProof::create([
                'invoice_id' => $locked->getKey(),
                'resident_id' => $locked->resident_id,
                'amount' => $amount,
                'paid_on' => $paidOn?->toDateString(),
                'sender_note' => $note === '' ? null : mb_substr($note, 0, 255),
                'status' => ProofStatus::Pending,
            ]);

            ($this->store)($proof, $file, AttachmentCategory::TransferProof, $actor);

            return $proof;
        });
    }
}
