<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/** Mengelola tenant lintas pemilik: khusus Super Admin aktif. */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->viewAny($user);
    }
}
