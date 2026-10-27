<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function invite(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Hanya pemilik, dan hanya untuk staf di tenant yang sama (scope tenant sudah menjamin 404 lintas tenant).
     */
    public function updatePermissions(User $user, User $staff): bool
    {
        return $user->isOwner() && $staff->isStaff() && $staff->tenant_id === $user->tenant_id;
    }
}
