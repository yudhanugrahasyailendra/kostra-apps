<?php

namespace App\Actions;

use App\Enums\ProofStatus;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectPaymentProof
{
    /** Tolak bukti dengan alasan wajib. Tidak ada uang yang dicatat dan tagihan tidak berubah. */
    public function handle(PaymentProof $proof, string $reason, User $by): PaymentProof
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi.']);
        }

        return DB::transaction(function () use ($proof, $reason, $by) {
            $proof = PaymentProof::query()->lockForUpdate()->findOrFail($proof->id);

            if ($proof->status !== ProofStatus::Pending) {
                throw ValidationException::withMessages(['proof' => 'Bukti ini sudah ditindak.']);
            }

            $proof->forceFill([
                'status' => ProofStatus::Rejected,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ])->save();

            return $proof;
        });
    }
}
