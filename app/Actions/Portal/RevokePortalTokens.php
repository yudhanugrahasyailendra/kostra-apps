<?php

namespace App\Actions\Portal;

use App\Models\PortalToken;
use App\Models\Resident;

class RevokePortalTokens
{
    /**
     * Mencabut satu tautan. Berlaku seketika: permintaan berikutnya dengan token itu ditolak.
     */
    public function one(PortalToken $token): PortalToken
    {
        if ($token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
        }

        return $token;
    }

    /**
     * Mencabut semua tautan aktif penghuni.
     *
     * @return int jumlah tautan yang dicabut
     */
    public function all(Resident $resident): int
    {
        return PortalToken::query()
            ->where('resident_id', $resident->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
