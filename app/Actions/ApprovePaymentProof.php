<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\ProofStatus;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Billing\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePaymentProof
{
    public function __construct(private RecordPayment $record) {}

    /**
     * Setujui bukti: buat pembayaran (dan kuitansi) untuk tagihan bukti itu, lalu tautkan.
     * Jumlah boleh dikoreksi pemilik bila berbeda dari pengakuan penghuni. Bila pencatatan gagal
     * (mis. tagihan sudah lunas lewat jalur lain), seluruh transaksi dibatalkan dan bukti tetap pending.
     */
    public function handle(PaymentProof $proof, User $by, ?int $amount = null, PaymentMethod $method = PaymentMethod::Transfer, ?int $paymentAccountId = null, ?string $paidOn = null): PaymentProof
    {
        return DB::transaction(function () use ($proof, $by, $amount, $method, $paymentAccountId, $paidOn) {
            $proof = PaymentProof::query()->lockForUpdate()->findOrFail($proof->id);

            if ($proof->status !== ProofStatus::Pending) {
                throw ValidationException::withMessages(['proof' => 'Bukti ini sudah ditindak.']);
            }

            $amount ??= $proof->amount;
            $date = $paidOn ?? $proof->paid_on?->toDateString();
            $date = $date === null ? BillingClock::today() : CarbonImmutable::parse($date);

            $payment = $this->record->handle(
                $proof->resident,
                $amount,
                $date,
                $method,
                [$proof->invoice_id => $amount],
                $by,
                $paymentAccountId,
                null,
                'Dari bukti transfer #'.$proof->id,
            );

            $proof->forceFill([
                'status' => ProofStatus::Approved,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'payment_id' => $payment->id,
            ])->save();

            return $proof;
        });
    }
}
