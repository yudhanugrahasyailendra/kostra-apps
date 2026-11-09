<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->isOwner() && $invitation->tenant_id === $user->tenant_id;
    }
}
