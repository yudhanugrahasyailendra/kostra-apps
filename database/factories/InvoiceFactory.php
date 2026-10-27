<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tagihan mentah untuk tes yang butuh data massal. Berikan tenant, properti, kontrak, dan penghuni
 * yang konsisten. Nomor harus diisi pemanggil bila membuat lebih dari satu.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'T-'.fake()->unique()->numerify('########'),
            'type' => InvoiceType::Manual,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => InvoiceStatus::Issued,
            'subtotal' => 1_500_000,
            'adjustment_total' => 0,
            'total' => 1_500_000,
            'paid_total' => 0,
            'issued_at' => now(),
        ];
    }
}
