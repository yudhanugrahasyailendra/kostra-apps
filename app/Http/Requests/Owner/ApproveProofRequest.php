<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\ParsesRoomInput;
use Illuminate\Foundation\Http\FormRequest;

class ApproveProofRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('proof'));
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoney('amount');
    }

    public function rules(): array
    {
        return [
            // Kosong = setujui sesuai klaim penghuni.
            'amount' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'Nominal disetujui'];
    }
}
