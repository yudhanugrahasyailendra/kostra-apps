<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('proof'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }
}
