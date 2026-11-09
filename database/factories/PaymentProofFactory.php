<?php

namespace Database\Factories;

use App\Enums\ProofStatus;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * invoice_id dan resident_id diberikan pemanggil.
 *
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => 1500000,
            'paid_on' => now()->toDateString(),
            'sender_note' => 'Transfer dari rekening penghuni',
            'status' => ProofStatus::Pending,
        ];
    }
}
