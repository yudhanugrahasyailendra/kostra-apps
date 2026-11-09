<?php

namespace App\Actions;

use App\Models\Resident;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

class SaveResident
{
    /**
     * Simpan penghuni baru atau perbarui yang ada. Nomor telepon selalu dinormalisasi di sini
     * sehingga semua jalur masuk (form, impor) menyimpan format yang sama.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Resident $resident = null): Resident
    {
        $phone = PhoneNumber::normalize($data['phone'] ?? null);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.']);
        }

        $attributes = [
            'name' => trim((string) $data['name']),
            'phone' => $phone,
            'email' => $this->text($data['email'] ?? null),
            'occupation' => $this->text($data['occupation'] ?? null),
            'emergency_name' => $this->text($data['emergency_name'] ?? null),
            'emergency_relation' => $this->text($data['emergency_relation'] ?? null),
            'emergency_phone' => PhoneNumber::normalize($data['emergency_phone'] ?? null),
            'notes' => $this->text($data['notes'] ?? null),
        ];

        // Persetujuan pengumpulan data dicatat sekali dan tidak dicabut lewat form ini.
        $consented = (bool) ($data['consented'] ?? false);

        if ($resident === null) {
            return Resident::create($attributes + [
                'is_active' => true,
                'consented_at' => $consented ? now() : null,
            ]);
        }

        if ($consented && $resident->consented_at === null) {
            $attributes['consented_at'] = now();
        }

        $resident->update($attributes);

        return $resident;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
