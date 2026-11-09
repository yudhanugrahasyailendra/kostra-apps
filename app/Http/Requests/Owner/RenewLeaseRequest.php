<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class RenewLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('renew', $this->route('lease'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'rent_amount' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['duration_months', 'rent_amount'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_months.min' => 'Durasi minimal 1 bulan.',
            'duration_months.max' => 'Durasi maksimal 120 bulan.',
            'rent_amount.integer' => 'Harga sewa harus berupa angka bulat rupiah.',
        ];
    }
}
