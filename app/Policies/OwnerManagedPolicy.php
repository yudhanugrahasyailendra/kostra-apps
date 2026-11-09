<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar Policy untuk data operasional: semua pengguna tenant boleh melihat,
 * hanya pemilik yang boleh membuat, mengubah, dan menghapus. Kecocokan
 * tenant_id diperiksa lagi sebagai lapis kedua setelah TenantScope.
 */
abstract class OwnerManagedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->tenant_id !== null && $model->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isOwner() && $model->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}
