<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class VoidInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'Alasan pembatalan'];
    }
}
