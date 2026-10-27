<?php

namespace App\Actions\Payments;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\PaymentMethod;
use App\Enums\ProofStatus;
use App\Events\PaymentRecorded;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePaymentProof
{
    use ResolvesTenant;

    public function __construct(private readonly RecordPayment $record) {}

    /**
     * Menyetujui bukti transfer: membuat pembayaran lewat RecordPayment yang sama (semua aturan uang berlaku),
     * lalu mengisi payment_proofs.payment_id. Peninjau boleh menyetujui nominal LEBIH KECIL dari klaim penghuni
     * (mis. yang masuk ternyata sebagian), tidak boleh lebih besar. Bukti hanya bisa ditindak sekali.
     */
    public function __invoke(PaymentProof $proof, User $actor, ?int $approvedAmount = null): Payment
    {
        $tenant = $this->currentTenant();

        $payment = DB::transaction(function () use ($proof, $actor, $approvedAmount, $tenant) {
            $locked = PaymentProof::query()->lockForUpdate()->findOrFail($proof->getKey());

            if ($locked->status !== ProofStatus::Pending) {
                throw ValidationException::withMessages(['general' => 'Bukti ini sudah ditindak.']);
            }

            $amount = $approvedAmount ?? $locked->amount;

            if ($amount < 1 || $amount > $locked->amount) {
                throw ValidationException::withMessages(['amount' => 'Nominal disetujui harus antara Rp 1 dan nominal yang diklaim penghuni.']);
            }

            try {
                $payment = $this->record->persist([
                    'resident_id' => $locked->resident_id,
                    'paid_on' => ($locked->paid_on ?? $tenant->today())->toDateString(),
                    'method' => PaymentMethod::Transfer->value,
                    'notes' => 'Dari bukti transfer #'.$locked->getKey().($locked->sender_note ? ': '.$locked->sender_note : ''),
                    'allocations' => [['invoice_id' => $locked->invoice_id, 'amount' => $amount]],
                ], $actor);
            } catch (ValidationException $e) {
                // Kesalahan aturan uang dilaporkan sebagai satu pesan umum di antrean verifikasi.
                throw ValidationException::withMessages(['general' => (string) collect($e->errors())->flatten()->first()]);
            }

            $locked->update([
                'status' => ProofStatus::Approved,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'payment_id' => $payment->getKey(),
            ]);

            return $payment;
        });

        event(new PaymentRecorded($payment->getKey(), $payment->tenant_id));

        return $payment;
    }
}
