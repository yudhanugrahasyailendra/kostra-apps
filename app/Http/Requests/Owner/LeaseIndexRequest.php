<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['all', 'active', 'expiring', 'ended'])],
            'q' => ['nullable', 'string', 'max:100'],
            'property_id' => ['nullable', 'integer'],
        ];
    }
}
