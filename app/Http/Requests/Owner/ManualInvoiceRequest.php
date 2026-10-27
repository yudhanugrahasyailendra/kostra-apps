<?php

namespace App\Http\Requests\Owner;

use App\Actions\Billing\CreateManualInvoice;
use App\Enums\InvoiceItemType;
use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Invoice;
use App\Models\Lease;
use App\Rules\ExistsInTenant;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualInvoiceRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['notes']);

        // Baris yang benar-benar kosong diabaikan; harga satuan dibaca sebagai rupiah bulat.
        $items = collect((array) $this->input('items', []))
            ->filter(fn ($item) => is_array($item) && (filled($item['description'] ?? null) || filled($item['unit_price'] ?? null)))
            ->map(fn (array $item) => [
                'type' => filled($item['type'] ?? null) ? $item['type'] : InvoiceItemType::Other->value,
                'description' => trim((string) ($item['description'] ?? '')),
                'quantity' => filled($item['quantity'] ?? null) ? trim((string) $item['quantity']) : '1',
                'unit_price' => filled($item['unit_price'] ?? null) ? (Rupiah::parse($item['unit_price']) ?? $item['unit_price']) : null,
            ])
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'lease_id' => ['required', new ExistsInTenant(Lease::class, 'Kontrak')],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:'.CreateManualInvoice::MAX_ITEMS],
            'items.*.type' => ['required', Rule::in(InvoiceItemType::manualValues())],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'items.*.unit_price' => ['required', 'integer', 'min:1', 'max:'.CreateManualInvoice::MAX_UNIT_PRICE],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Isi sekurangnya satu item tagihan.',
            'items.min' => 'Isi sekurangnya satu item tagihan.',
            'items.*.description.required' => 'Deskripsi item wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
            'items.*.unit_price.integer' => 'Harga satuan harus rupiah bulat.',
            'items.*.quantity.regex' => 'Kuantitas harus angka dengan paling banyak 2 desimal.',
        ];
    }

    public function attributes(): array
    {
        return ['due_date' => 'Jatuh tempo'];
    }
}
