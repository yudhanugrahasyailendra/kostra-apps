<?php

namespace App\Http\Requests\Owner;

use App\Enums\Permission;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', User::class);
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'email' => filled($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : null,
            'phone' => filled($phone) ? (PhoneNumber::normalize($phone) ?? $phone) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+[1-9]\d{7,14}$/'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }
}
