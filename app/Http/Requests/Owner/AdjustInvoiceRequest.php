<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjust', $this->route('invoice'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Bertanda: positif menambah, negatif mengurangi.
            'amount' => ['required', 'integer', 'not_in:0', 'between:-4294967295,4294967295'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah penyesuaian wajib diisi.',
            'amount.integer' => 'Jumlah penyesuaian harus berupa angka bulat rupiah.',
            'amount.not_in' => 'Jumlah penyesuaian tidak boleh nol.',
            'amount.between' => 'Jumlah penyesuaian terlalu besar.',
            'reason.required' => 'Alasan penyesuaian wajib diisi.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }
}
