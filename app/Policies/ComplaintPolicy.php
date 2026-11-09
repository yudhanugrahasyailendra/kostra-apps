<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

/**
 * Pemilik dan semua staf menangani komplain. Tidak ada aksi `delete`: tiket hanya ditutup.
 * Kecocokan tenant_id diperiksa lagi sebagai lapis kedua setelah TenantScope.
 */
class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
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
