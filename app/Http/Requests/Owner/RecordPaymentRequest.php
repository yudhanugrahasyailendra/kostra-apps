<?php

namespace App\Http\Requests\Owner;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_account_id' => ['nullable', 'integer', Rule::exists('payment_accounts', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1', 'max:50'],
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'allocations.*.amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resident_id.required' => 'Pilih penghuni yang membayar.',
            'resident_id.exists' => 'Penghuni tidak ditemukan.',
            'amount.required' => 'Jumlah pembayaran wajib diisi.',
            'amount.integer' => 'Jumlah pembayaran harus berupa angka bulat rupiah.',
            'amount.min' => 'Jumlah pembayaran minimal Rp 1.',
            'paid_on.required' => 'Tanggal pembayaran wajib diisi.',
            'paid_on.date_format' => 'Tanggal pembayaran tidak valid.',
            'method.required' => 'Pilih metode pembayaran.',
            'method.enum' => 'Metode pembayaran tidak dikenal.',
            'payment_account_id.exists' => 'Rekening tujuan tidak ditemukan.',
            'reference.max' => 'Nomor referensi maksimal 100 karakter.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
            'allocations.required' => 'Pilih minimal satu tagihan yang dibayar.',
            'allocations.min' => 'Pilih minimal satu tagihan yang dibayar.',
            'allocations.*.invoice_id.exists' => 'Tagihan tidak ditemukan.',
            'allocations.*.invoice_id.distinct' => 'Tagihan yang sama dipilih lebih dari sekali.',
            'allocations.*.amount.required' => 'Jumlah tiap tagihan wajib diisi.',
            'allocations.*.amount.integer' => 'Jumlah tiap tagihan harus berupa angka bulat rupiah.',
            'allocations.*.amount.min' => 'Jumlah tiap tagihan minimal Rp 1.',
        ];
    }
}
