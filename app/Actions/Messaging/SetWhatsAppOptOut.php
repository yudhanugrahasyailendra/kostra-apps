<?php

namespace App\Actions\Messaging;

use App\Models\Resident;

class SetWhatsAppOptOut
{
    /**
     * Mencatat penghuni berhenti (atau kembali) menerima pesan otomatis. Pesan yang sudah diantrekan dicek ulang
     * saat dikirim, jadi berhenti berlaku seketika.
     */
    public function __invoke(Resident $resident, bool $optOut): Resident
    {
        $resident->update(['whatsapp_opt_out_at' => $optOut ? ($resident->whatsapp_opt_out_at ?? now()) : null]);

        return $resident;
    }
}
