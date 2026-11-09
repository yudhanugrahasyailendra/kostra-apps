<?php

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\LeaseDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaseDeposit> */
class LeaseDepositFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => 1000000,
            'received_at' => now()->toDateString(),
            'status' => DepositStatus::Held,
        ];
    }
}
