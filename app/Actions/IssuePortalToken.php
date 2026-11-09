<?php

namespace App\Actions;

use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\User;

class IssuePortalToken
{
    /**
     * Buat token baru untuk penghuni dan kembalikan tautan lengkapnya. Token asli hanya hidup di nilai
     * kembalian ini (untuk disisipkan ke pesan WhatsApp); di basis data hanya hash-nya. Token lama tidak
     * dicabut, jadi tautan di pesan sebelumnya tetap berlaku sampai kedaluwarsa atau dicabut pemilik.
     * Harus dipanggil di dalam konteks tenant penghuni.
     */
    public function url(Resident $resident, ?User $by = null): string
    {
        $token = PortalToken::generate();

        PortalToken::create([
            'resident_id' => $resident->id,
            'token_hash' => PortalToken::hash($token),
            'expires_at' => now()->addDays((int) config('kostra.portal.token_ttl_days')),
            'created_by' => $by?->id,
        ]);

        return url('/p/'.$token);
    }
}
