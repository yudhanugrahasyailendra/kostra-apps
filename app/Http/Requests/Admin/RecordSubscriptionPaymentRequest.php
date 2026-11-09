<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class RecordSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tenant') ?? Tenant::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Isi metode pembayaran, misalnya Transfer BCA.',
            'payment_method.max' => 'Metode pembayaran maksimal 40 karakter.',
            'reference.max' => 'Referensi maksimal 120 karakter.',
        ];
    }
}
