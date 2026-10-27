<?php

namespace App\Http\Requests\Owner;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updatePermissions', $this->route('staff'));
    }

    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }
}
