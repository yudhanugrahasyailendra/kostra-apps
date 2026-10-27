<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\PaymentProof;
use Illuminate\Foundation\Http\FormRequest;

class SubmitProofRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('submit', PaymentProof::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['paid_on', 'sender_note']);
        $this->normalizeMoney('amount');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'sender_note' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('kostra.attachments.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'Nominal', 'paid_on' => 'Tanggal transfer', 'sender_note' => 'Catatan pengirim', 'file' => 'Bukti transfer'];
    }
}
