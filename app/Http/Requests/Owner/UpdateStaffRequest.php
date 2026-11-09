<?php

namespace App\Http\Requests\Owner;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('staff'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
