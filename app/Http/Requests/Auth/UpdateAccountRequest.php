<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor WhatsApp',
            'current_password' => 'Kata sandi saat ini',
            'password' => 'Kata sandi baru',
        ];
    }

    public function messages(): array
    {
        return ['current_password.current_password' => 'Kata sandi saat ini tidak cocok.'];
    }
}
