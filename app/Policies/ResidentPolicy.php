<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function view(User $user, Resident $resident): bool
    {
        return $this->viewAny($user) && $resident->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Resident $resident): bool
    {
        return $this->view($user, $resident);
    }

    /**
     * Penghapusan data (anonimisasi) hanya untuk pemilik.
     */
    public function delete(User $user, Resident $resident): bool
    {
        return $user->isOwner() && $resident->tenant_id === $user->tenant_id;
    }
}
