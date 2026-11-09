<?php

namespace App\Http\Requests\Owner;

use App\Models\Resident;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident instanceof Resident
            ? $this->user()->can('update', $resident)
            : $this->user()->can('create', Resident::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', $this->phoneRule('Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.')],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:30', $this->phoneRule('Nomor telepon darurat tidak valid.')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'consented' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 120 karakter.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'phone.max' => 'Nomor WhatsApp terlalu panjang.',
            'email.email' => 'Format email tidak valid.',
            'occupation.max' => 'Pekerjaan atau kampus maksimal 120 karakter.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
        ];
    }

    private function phoneRule(string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($message): void {
            if (PhoneNumber::normalize((string) $value) === null) {
                $fail($message);
            }
        };
    }
}
