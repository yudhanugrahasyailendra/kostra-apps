<?php

namespace App\Policies;

use App\Models\RoomType;
use App\Models\User;

class RoomTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, RoomType $type): bool
    {
        return $user->isOwner() && $type->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, RoomType $type): bool
    {
        return $this->update($user, $type);
    }
}
