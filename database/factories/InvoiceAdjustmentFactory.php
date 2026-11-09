<?php

namespace Database\Factories;

use App\Models\InvoiceAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceAdjustment> */
class InvoiceAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return ['amount' => -50000, 'reason' => 'Potongan uji'];
    }
}
