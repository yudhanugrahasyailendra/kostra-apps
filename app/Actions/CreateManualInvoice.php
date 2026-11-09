<?php

namespace App\Actions;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceIssued;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\User;
use App\Services\Billing\BillingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateManualInvoice
{
    public function __construct(private RecalculateInvoice $recalculate, private GenerateInvoiceNumber $numbers) {}

    /**
     * Tagihan insidental untuk satu kontrak (TGH-04), di luar siklus bulanan.
     *
     * Kunci `data`: due_date (Y-m-d), notes, items (list type, description, quantity, unit_price).
     * Jumlah tiap item = quantity x unit_price, dibulatkan ke rupiah tanpa float.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Lease $lease, array $data, User $by): Invoice
    {
        $items = collect($data['items'] ?? [])->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu item tagihan.']);
        }

        $today = BillingClock::today();
        $due = CarbonImmutable::parse($data['due_date'] ?? $today->toDateString());

        if ($due->lt($today)) {
            throw ValidationException::withMessages(['due_date' => 'Jatuh tempo tidak boleh sebelum tanggal terbit.']);
        }

        return DB::transaction(function () use ($lease, $data, $by, $items, $today, $due) {
            $invoice = Invoice::create([
                'property_id' => $lease->property_id,
                'lease_id' => $lease->id,
                'resident_id' => $lease->primary_resident_id,
                'number' => $this->numbers->handle($today->format('Y-m'), BillingSetting::current()->invoice_prefix),
                'type' => InvoiceType::Manual,
                'issue_date' => $today->toDateString(),
                'due_date' => $due->toDateString(),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'created_by' => $by->id,
                'notes' => ($notes = trim((string) ($data['notes'] ?? ''))) === '' ? null : $notes,
            ]);

            foreach ($items as $index => $item) {
                $unitPrice = (int) $item['unit_price'];
                // Kuantitas dua desimal diubah ke persen agar perkalian memakai bilangan bulat.
                $hundredths = (int) round(((float) ($item['quantity'] ?? 1)) * 100);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'type' => $item['type'] ?? InvoiceItemType::Other,
                    'description' => trim((string) $item['description']),
                    'quantity' => $hundredths / 100,
                    'unit_price' => $unitPrice,
                    'amount' => intdiv($hundredths * $unitPrice + 50, 100),
                    'sort_order' => $index,
                ]);
            }

            $invoice = $this->recalculate->handle($invoice);
            InvoiceIssued::dispatch($invoice);

            return $invoice;
        });
    }
}
