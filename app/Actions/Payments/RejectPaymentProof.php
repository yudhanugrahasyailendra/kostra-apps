<?php

namespace App\Actions\Payments;

use App\Enums\ProofStatus;
use App\Events\ProofRejected;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectPaymentProof
{
    /**
     * Menolak bukti transfer. Alasan wajib (dilihat penghuni). Tidak mengubah tagihan.
     */
    public function __invoke(PaymentProof $proof, string $reason, User $actor): PaymentProof
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi (3 sampai 255 karakter).']);
        }

        $locked = DB::transaction(function () use ($proof, $reason, $actor) {
            $locked = PaymentProof::query()->lockForUpdate()->findOrFail($proof->getKey());

            if ($locked->status !== ProofStatus::Pending) {
                throw ValidationException::withMessages(['general' => 'Bukti ini sudah ditindak.']);
            }

            $locked->update([
                'status' => ProofStatus::Rejected,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ]);

            return $locked;
        });

        event(new ProofRejected($locked->getKey(), $locked->tenant_id));

        return $locked;
    }
}
