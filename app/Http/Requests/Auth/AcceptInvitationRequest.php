<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'phone' => filled($phone) ? (PhoneNumber::normalize($phone) ?? $phone) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Keunikan email/nomor bersifat global (satu pengguna satu tenant), jadi
        // pemeriksaan ini sengaja lintas tenant.
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+62\d{8,13}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'email.required_without' => 'Isi email atau nomor WhatsApp.',
            'phone.required_without' => 'Isi email atau nomor WhatsApp.',
        ];
    }
}
