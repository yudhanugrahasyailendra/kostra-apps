<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class AssignComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complaint'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['assigned_to' => filled($this->input('assigned_to')) ? $this->input('assigned_to') : null]);
    }

    public function rules(): array
    {
        // Kepemilikan tenant diperiksa AssignComplaint lewat model ber-scope.
        return [
            'assigned_to' => ['nullable', 'integer'],
        ];
    }
}
