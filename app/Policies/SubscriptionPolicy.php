<?php

namespace App\Policies;

use App\Models\User;

/** Langganan Kostra milik tenant: hanya pemilik yang melihat dan mengubah paket (staf tidak). */
class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }
}
