<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Detail satu penghuni: tambahan catatan internal pemilik (PNG-07) dan status persetujuan. */
class ResidentDetailResource extends ResidentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'email' => $this->email,
            'notes' => $this->notes,
            'consented_at' => $this->consented_at?->toIso8601String(),
            'whatsapp_opted_out' => $this->whatsapp_opt_out_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
