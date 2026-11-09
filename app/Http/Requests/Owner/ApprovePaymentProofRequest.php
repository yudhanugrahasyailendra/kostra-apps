<?php

namespace App\Http\Requests\Owner;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovePaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('proof'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_account_id' => ['nullable', 'integer', Rule::exists('payment_accounts', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.integer' => 'Jumlah harus berupa angka bulat rupiah.',
            'amount.min' => 'Jumlah minimal Rp 1.',
            'method.required' => 'Pilih metode pembayaran.',
            'method.enum' => 'Metode pembayaran tidak dikenal.',
            'payment_account_id.exists' => 'Rekening tujuan tidak ditemukan.',
            'paid_on.date_format' => 'Tanggal pembayaran tidak valid.',
        ];
    }
}
