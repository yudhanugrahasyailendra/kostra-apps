<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppOptOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('resident'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['opt_out' => $this->boolean('opt_out')]);
    }

    public function rules(): array
    {
        return [
            'opt_out' => ['boolean'],
        ];
    }
}
