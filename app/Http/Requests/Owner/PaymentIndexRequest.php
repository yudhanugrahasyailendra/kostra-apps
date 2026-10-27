<?php

namespace App\Http\Requests\Owner;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // YYYY-MM menurut tanggal bayar, atau "all".
            'period' => ['nullable', 'regex:/^(all|\d{4}-(0[1-9]|1[0-2]))$/'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'status' => ['nullable', Rule::in(['active', 'voided'])],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
