<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pengelolaan staf: hanya pemilik. Model User sudah terscope tenant, jadi
 * pemeriksaan tenant di sini adalah lapis kedua.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isOwner() && $target->tenant_id === $user->tenant_id && ! $target->isOwner();
    }

    public function delete(User $user, User $target): bool
    {
        return $this->update($user, $target);
    }
}
