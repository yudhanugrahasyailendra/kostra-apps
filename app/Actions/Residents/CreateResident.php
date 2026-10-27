<?php

namespace App\Actions\Residents;

use App\Models\Resident;

class CreateResident
{
    /**
     * Persetujuan pengumpulan data dicatat saat penghuni dibuat (consented_at).
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): Resident
    {
        return Resident::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'emergency_name' => $data['emergency_name'] ?? null,
            'emergency_relation' => $data['emergency_relation'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'consented_at' => now(),
            'is_active' => true,
        ]);
    }
}
