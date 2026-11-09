<?php

namespace Database\Factories;

use App\Enums\PaymentAccountType;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAccount> */
class PaymentAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => PaymentAccountType::Bank,
            'bank_name' => 'BCA',
            'account_number' => (string) fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
