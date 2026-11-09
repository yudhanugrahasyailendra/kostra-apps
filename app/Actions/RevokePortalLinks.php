<?php

namespace App\Actions;

use App\Models\PortalToken;
use App\Models\Resident;
use App\Services\AuditLogger;

class RevokePortalLinks
{
    /** Cabut semua tautan portal yang masih berlaku milik satu penghuni. Akses langsung tertutup. */
    public function handle(Resident $resident): int
    {
        $revoked = PortalToken::query()->usable()->where('resident_id', $resident->id)->update(['revoked_at' => now(), 'updated_at' => now()]);

        if ($revoked > 0) {
            app(AuditLogger::class)->record('portal.revoked', $resident, null, ['count' => $revoked]);
        }

        return $revoked;
    }
}
