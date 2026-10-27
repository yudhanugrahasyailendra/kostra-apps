<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function view(User $user, Lease $lease): bool
    {
        return $this->viewAny($user) && $lease->tenant_id === $user->tenant_id;
    }

    /**
     * Check-in, check-out, perpanjangan, dan pindah kamar adalah pekerjaan harian: pemilik dan staf.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function operate(User $user, Lease $lease): bool
    {
        return $this->view($user, $lease);
    }

    /**
     * Uang yang berubah (potongan deposit, perubahan harga sewa) hanya diputuskan pemilik.
     */
    public function decideMoney(User $user, Lease $lease): bool
    {
        return $user->isOwner() && $lease->tenant_id === $user->tenant_id;
    }
}
