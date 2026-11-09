<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemilik dan staf mencatat dan mengubah penghuni (staf penjaga kos mendaftarkan penghuni baru);
 * hanya pemilik yang boleh menghapus.
 */
class ResidentPolicy extends OwnerManagedPolicy
{
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Model $model): bool
    {
        return $user->tenant_id !== null && $model->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->isOwner() && $model->tenant_id === $user->tenant_id;
    }
}
