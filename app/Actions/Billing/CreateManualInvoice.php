<?php

namespace App\Actions\Billing;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceIssued;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\User;
use App\Support\InvoiceMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateManualInvoice
{
    use ResolvesTenant;

    public const MAX_ITEMS = 20;

    public const MAX_UNIT_PRICE = 1_000_000_000;

    public function __construct(
        private readonly GenerateInvoiceNumber $numbers,
        private readonly RecalculateInvoice $recalculate,
    ) {}

    /**
     * Tagihan manual/insidental untuk satu kontrak (di luar siklus bulanan). Langsung terbit.
     *
     * @param  array<string, mixed>  $data  items[{description, quantity, unit_price, type}], due_date, [notes]
     */
    public function __invoke(Lease $lease, array $data, ?User $actor = null): Invoice
    {
        $tenant = $this->currentTenant();
        $today = $tenant->today();

        $items = array_values($data['items'] ?? []);

        if ($items === [] || count($items) > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['items' => 'Isi 1 sampai '.self::MAX_ITEMS.' item tagihan.']);
        }

        $due = is_string($data['due_date'] ?? null) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['due_date'], $tenant->timezone) : false;

        if ($due === false) {
            throw ValidationException::withMessages(['due_date' => 'Format tanggal harus YYYY-MM-DD.']);
        }

        if ($due->lt($today) || $due->gt($today->addYear())) {
            throw ValidationException::withMessages(['due_date' => 'Jatuh tempo harus antara hari ini dan satu tahun ke depan.']);
        }

        $lines = [];

        foreach ($items as $index => $item) {
            $lines[] = $this->line($index, $item);
        }

        $invoice = DB::transaction(function () use ($lease, $data, $actor, $today, $due, $lines) {
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());
            $settings = BillingSetting::current();

            $invoice = Invoice::create([
                'property_id' => $lease->property_id,
                'lease_id' => $lease->getKey(),
                'resident_id' => $lease->primary_resident_id,
                'number' => ($this->numbers)('invoice', $settings->invoice_prefix, $today),
                'type' => InvoiceType::Manual,
                'period' => null,
                'issue_date' => $today->toDateString(),
                'due_date' => $due->toDateString(),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'created_by' => $actor?->getKey(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $position => $line) {
                InvoiceItem::create([...$line, 'invoice_id' => $invoice->getKey(), 'sort_order' => min($position, 255)]);
            }

            return ($this->recalculate)($invoice);
        });

        event(new InvoiceIssued($invoice->getKey(), $invoice->tenant_id));

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function line(int $index, array $item): array
    {
        $field = fn (string $name) => "items.{$index}.{$name}";

        $description = trim((string) ($item['description'] ?? ''));
        $type = InvoiceItemType::tryFrom((string) ($item['type'] ?? InvoiceItemType::Other->value));
        $unitPrice = $item['unit_price'] ?? null;
        $quantity = (string) ($item['quantity'] ?? '1');

        if ($description === '' || mb_strlen($description) > 255) {
            throw ValidationException::withMessages([$field('description') => 'Deskripsi item wajib diisi (maksimal 255 karakter).']);
        }

        if ($type === null) {
            throw ValidationException::withMessages([$field('type') => 'Jenis item tidak valid.']);
        }

        if (! is_int($unitPrice) || $unitPrice < 1 || $unitPrice > self::MAX_UNIT_PRICE) {
            throw ValidationException::withMessages([$field('unit_price') => 'Harga satuan harus rupiah bulat dan lebih dari 0.']);
        }

        try {
            $amount = InvoiceMath::lineAmount($quantity, $unitPrice);
            $hundredths = InvoiceMath::quantityToHundredths($quantity);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field('quantity') => 'Kuantitas harus angka dengan paling banyak 2 desimal.']);
        }

        if ($hundredths < 1) {
            throw ValidationException::withMessages([$field('quantity') => 'Kuantitas harus lebih dari 0.']);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([$field('unit_price') => 'Jumlah item harus lebih dari Rp 0.']);
        }

        return [
            'type' => $type,
            'description' => $description,
            'quantity' => number_format($hundredths / 100, 2, '.', ''),
            'unit_price' => $unitPrice,
            'amount' => $amount,
        ];
    }
}
