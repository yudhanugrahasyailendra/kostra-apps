<?php

namespace App\Http\Requests\Owner;

use App\Enums\PaymentAccountType;
use App\Models\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('paymentAccount');

        return $account instanceof PaymentAccount
            ? $this->user()->can('update', $account)
            : $this->user()->can('create', PaymentAccount::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PaymentAccountType::class)],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'bank_name' => ['nullable', 'required_if:type,bank,ewallet', 'string', 'max:100'],
            'account_number' => ['nullable', 'required_if:type,bank,ewallet', 'string', 'max:50'],
            'account_name' => ['nullable', 'required_if:type,bank,ewallet', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => 'Pilih jenis rekening.',
            'type.enum' => 'Jenis rekening tidak dikenal.',
            'property_id.exists' => 'Properti tidak ditemukan.',
            'bank_name.required_if' => 'Nama bank atau dompet digital wajib diisi.',
            'account_number.required_if' => 'Nomor rekening wajib diisi.',
            'account_name.required_if' => 'Nama pemilik rekening wajib diisi.',
            'bank_name.max' => 'Nama bank maksimal 100 karakter.',
            'account_number.max' => 'Nomor rekening maksimal 50 karakter.',
            'account_name.max' => 'Nama pemilik maksimal 100 karakter.',
            'instructions.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
