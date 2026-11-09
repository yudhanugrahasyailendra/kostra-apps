<?php

namespace Database\Factories;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pabrik data uji. property_id, lease_id, dan resident_id diberikan pemanggil, atau lewat
 * `forLease()`. Angka diisi langsung (tanpa item) agar tes bebas menyusun skenario; tes yang
 * menguji perhitungan membuat item dan memanggil RecalculateInvoice.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $date = now()->startOfMonth();

        return [
            'number' => 'INV-'.$date->format('Y-m').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'type' => InvoiceType::Recurring,
            'period' => $date->format('Y-m'),
            'period_start' => $date->toDateString(),
            'period_end' => $date->copy()->endOfMonth()->toDateString(),
            'issue_date' => $date->toDateString(),
            'due_date' => $date->copy()->addDays(9)->toDateString(),
            'status' => InvoiceStatus::Issued,
            'subtotal' => 1500000,
            'adjustment_total' => 0,
            'total' => 1500000,
            'paid_total' => 0,
            'issued_at' => now(),
        ];
    }

    /** Tagihan nyata selalu punya item; satu item sewa seharga subtotal dibuat agar hitung ulang konsisten. */
    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            if ($invoice->subtotal > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'type' => InvoiceItemType::Rent,
                    'description' => 'Sewa kamar',
                    'quantity' => 1,
                    'unit_price' => $invoice->subtotal,
                    'amount' => $invoice->subtotal,
                    'sort_order' => 0,
                ]);
            }
        });
    }

    public function forLease(Lease $lease): static
    {
        return $this->state(fn () => [
            'property_id' => $lease->property_id,
            'lease_id' => $lease->id,
            'resident_id' => $lease->primary_resident_id,
        ]);
    }

    public function status(InvoiceStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function manual(): static
    {
        return $this->state(['type' => InvoiceType::Manual, 'period' => null, 'period_start' => null, 'period_end' => null]);
    }
}
