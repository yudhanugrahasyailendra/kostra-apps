<?php

namespace App\Http\Requests\Owner;

use App\Actions\Billing\AddInvoiceAdjustment;
use App\Http\Requests\Concerns\ParsesRoomInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInvoiceRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        return $this->user()->can('adjust', $this->route('invoice'));
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoney('amount');
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'integer', 'min:1', 'max:'.AddInvoiceAdjustment::MAX_AMOUNT],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['direction' => 'Jenis penyesuaian', 'amount' => 'Nominal', 'reason' => 'Alasan'];
    }
}
