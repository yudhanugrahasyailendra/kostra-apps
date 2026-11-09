<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Semua pengguna tenant (pemilik dan staf penjaga) boleh melihat, check-in, dan check-out.
 * Perpanjangan dan pindah kamar mengubah harga sewa, jadi hanya pemilik (lewat `update`).
 */
class LeasePolicy extends OwnerManagedPolicy
{
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function checkOut(User $user, Model $model): bool
    {
        return $user->tenant_id !== null && $model->tenant_id === $user->tenant_id;
    }

    public function renew(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    public function transfer(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}
