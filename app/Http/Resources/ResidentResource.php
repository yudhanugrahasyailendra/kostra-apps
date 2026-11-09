<?php

namespace App\Http\Resources;

use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk penghuni di daftar. Catatan internal hanya di ResidentDetailResource.
 * `has_ktp` dan `has_duplicate_phone` diisi pemanggil (withExists / pemeriksaan satu halaman).
 *
 * @mixin Resident
 */
class ResidentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'occupation' => $this->occupation,
            'emergency_name' => $this->emergency_name,
            'emergency_relation' => $this->emergency_relation,
            'emergency_phone' => $this->emergency_phone,
            'is_active' => $this->is_active,
            'status_label' => $this->is_active ? 'Aktif' : 'Alumni',
            'status_tone' => $this->is_active ? 'lunas' : 'kosong',
            'has_ktp' => (bool) ($this->resource->getAttribute('has_ktp') ?? false),
            'has_duplicate_phone' => (bool) ($this->resource->getAttribute('has_duplicate_phone') ?? false),
        ];
    }
}
