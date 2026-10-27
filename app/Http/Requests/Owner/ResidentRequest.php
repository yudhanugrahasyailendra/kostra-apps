<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\ParsesRoomInput;
use App\Models\Resident;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class ResidentRequest extends FormRequest
{
    use ParsesRoomInput;

    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident
            ? $this->user()->can('update', $resident)
            : $this->user()->can('create', Resident::class);
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['email', 'occupation', 'emergency_name', 'emergency_relation', 'emergency_phone', 'notes']);

        foreach (['phone', 'emergency_phone'] as $key) {
            $value = $this->input($key);
            $this->merge([$key => filled($value) ? (PhoneNumber::normalize($value) ?? $value) : null]);
        }

        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $creating = $this->route('resident') === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Persetujuan penghuni atas pengumpulan datanya dicatat saat dibuat (consented_at).
            'consent' => [$creating ? 'accepted' : 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.',
            'emergency_phone.regex' => 'Nomor kontak darurat tidak valid.',
            'consent.accepted' => 'Centang bahwa penghuni sudah menyetujui datanya dikumpulkan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'emergency_name' => 'Nama kontak darurat',
            'emergency_phone' => 'Nomor kontak darurat',
            'occupation' => 'Pekerjaan atau kampus',
        ];
    }
}
