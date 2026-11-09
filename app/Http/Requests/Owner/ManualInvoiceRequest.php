<?php

namespace App\Http\Requests\Owner;

use App\Enums\InvoiceItemType;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Rule::exists dengan tenant_id: kontrak tenant lain dianggap tidak ada.
            'lease_id' => ['required', 'integer', Rule::exists('leases', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.type' => ['required', Rule::enum(InvoiceItemType::class)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'items.*.unit_price' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lease_id.required' => 'Pilih kontrak penghuni yang ditagih.',
            'lease_id.exists' => 'Kontrak tidak ditemukan.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.date_format' => 'Tanggal jatuh tempo tidak valid.',
            'items.required' => 'Tambahkan minimal satu item tagihan.',
            'items.min' => 'Tambahkan minimal satu item tagihan.',
            'items.max' => 'Maksimal 20 item per tagihan.',
            'items.*.type.required' => 'Pilih jenis item.',
            'items.*.description.required' => 'Keterangan item wajib diisi.',
            'items.*.description.max' => 'Keterangan item maksimal 255 karakter.',
            'items.*.quantity.required' => 'Jumlah item wajib diisi.',
            'items.*.quantity.numeric' => 'Jumlah item harus berupa angka.',
            'items.*.quantity.min' => 'Jumlah item minimal 0,01.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
            'items.*.unit_price.integer' => 'Harga satuan harus berupa angka bulat rupiah.',
            'items.*.unit_price.min' => 'Harga satuan minimal Rp 1.',
        ];
    }
}
