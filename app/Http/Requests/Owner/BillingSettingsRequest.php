<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class BillingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('invoice_prefix'))) {
            $this->merge(['invoice_prefix' => strtoupper(trim($this->input('invoice_prefix')))]);
        }

        $this->merge([
            'prorate_first_month' => $this->boolean('prorate_first_month'),
            'prorate_last_month' => $this->boolean('prorate_last_month'),
        ]);
    }

    public function rules(): array
    {
        return [
            'invoice_prefix' => ['required', 'regex:/^[A-Z]{2,10}$/'],
            // 1 sampai 28 supaya tanggal selalu ada di bulan Februari.
            'issue_day' => ['required', 'integer', 'min:1', 'max:28'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'prorate_first_month' => ['boolean'],
            'prorate_last_month' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['invoice_prefix.regex' => 'Awalan nomor tagihan harus 2 sampai 10 huruf (A-Z).'];
    }

    public function attributes(): array
    {
        return [
            'invoice_prefix' => 'Awalan nomor tagihan',
            'issue_day' => 'Tanggal terbit',
            'due_day' => 'Tanggal jatuh tempo',
        ];
    }
}
