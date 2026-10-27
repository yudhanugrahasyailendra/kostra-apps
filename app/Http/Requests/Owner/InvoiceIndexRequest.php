<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['all', 'issued', 'partial', 'paid', 'overdue', 'void'])],
            // YYYY-MM menurut tanggal terbit, atau "all" untuk semua periode.
            'period' => ['nullable', 'regex:/^(all|\d{4}-(0[1-9]|1[0-2]))$/'],
            'property_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
