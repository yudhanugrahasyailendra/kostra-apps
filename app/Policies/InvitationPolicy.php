<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function revoke(User $user, Invitation $invitation): bool
    {
        return $user->isOwner() && $invitation->tenant_id === $user->tenant_id;
    }
}
