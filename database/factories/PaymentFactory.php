<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * resident_id dan recorded_by diberikan pemanggil. Pembuatan langsung tidak membuat alokasi dan
 * tidak menghitung ulang tagihan; tes uang memakai Action RecordPayment.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => 1500000,
            'paid_on' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Active,
        ];
    }
}
