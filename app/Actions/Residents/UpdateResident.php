<?php

namespace App\Actions\Residents;

use App\Models\Resident;

class UpdateResident
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Resident $resident, array $data): Resident
    {
        $resident->update(array_intersect_key($data, array_flip([
            'name', 'phone', 'email', 'occupation',
            'emergency_name', 'emergency_relation', 'emergency_phone',
            'notes', 'is_active',
        ])));

        return $resident;
    }
}
