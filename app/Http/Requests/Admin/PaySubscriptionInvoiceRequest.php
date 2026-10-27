<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaySubscriptionInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.manage');
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_method' => 'Metode pembayaran', 'reference' => 'Referensi', 'paid_on' => 'Tanggal transfer'];
    }
}
