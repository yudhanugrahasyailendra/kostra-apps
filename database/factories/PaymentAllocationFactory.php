<?php

namespace Database\Factories;

use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAllocation> */
class PaymentAllocationFactory extends Factory
{
    public function definition(): array
    {
        return ['amount' => 1500000];
    }
}
