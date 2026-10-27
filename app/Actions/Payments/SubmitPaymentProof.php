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
use App\Models\Resident;
use App\Models\User;
use App\Support\Dates;
use App\Support\Rupiah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPaymentProof
{
    use ResolvesTenant;

    /** Bukti transfer yang boleh menunggu verifikasi per tagihan. */
    public const MAX_PENDING_PER_INVOICE = 3;

    public function __construct(
        private readonly StoreAttachment $store,
        private readonly InvoicePaidTotal $paidTotal,
    ) {}

    /**
     * Mencatat bukti transfer (menunggu verifikasi). Dicatat oleh staf atas nama penghuni, atau oleh
     * penghuni sendiri lewat portal ($actor berupa Resident). Tidak mengubah tagihan sebelum disetujui.
     *
     * @param  array<string, mixed>  $data  amount, [paid_on, sender_note]
     */
    public function __invoke(Invoice $invoice, array $data, UploadedFile $file, User|Resident|null $actor = null): PaymentProof
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

            // Satu tautan tidak boleh memenuhi antrean verifikasi dan disk: bukti menunggu per tagihan dibatasi.
            if (PaymentProof::query()->where('invoice_id', $locked->getKey())->pending()->count() >= self::MAX_PENDING_PER_INVOICE) {
                throw ValidationException::withMessages(['general' => 'Sudah ada '.self::MAX_PENDING_PER_INVOICE.' bukti yang menunggu diperiksa untuk tagihan ini. Tunggu pengelola memeriksanya.']);
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
