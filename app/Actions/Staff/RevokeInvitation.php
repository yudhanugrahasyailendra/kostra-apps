<?php

namespace App\Actions\Staff;

use App\Models\Invitation;

class RevokeInvitation
{
    /**
     * Mencabut undangan dengan mengakhiri masa berlakunya (skema tidak punya kolom revoked_at).
     */
    public function __invoke(Invitation $invitation): void
    {
        if ($invitation->accepted_at === null) {
            $invitation->update(['expires_at' => now()->subSecond()]);
        }
    }
}
