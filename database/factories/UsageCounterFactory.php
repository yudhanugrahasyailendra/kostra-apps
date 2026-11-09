<?php

namespace Database\Factories;

use App\Models\UsageCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UsageCounter> */
class UsageCounterFactory extends Factory
{
    public function definition(): array
    {
        return ['metric' => 'whatsapp_messages', 'period' => now()->format('Y-m'), 'used' => 0];
    }
}
