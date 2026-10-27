<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

/**
 * Tiket dikelola pemilik dan semua staf. Tidak ada kemampuan hapus: tiket hanya ditutup.
 */
class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return $this->viewAny($user) && $complaint->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $this->view($user, $complaint);
    }
}
