<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class BillingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'issue_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'prorate_first_month' => ['required', 'boolean'],
            'prorate_last_month' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'invoice_prefix.required' => 'Awalan nomor tagihan wajib diisi.',
            'invoice_prefix.max' => 'Awalan maksimal 10 karakter.',
            'invoice_prefix.regex' => 'Awalan hanya boleh huruf dan angka, tanpa spasi.',
            'issue_day.required' => 'Tanggal terbit wajib diisi.',
            'issue_day.between' => 'Tanggal terbit antara 1 dan 31.',
            'due_day.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_day.between' => 'Tanggal jatuh tempo antara 1 dan 31.',
        ];
    }
}
