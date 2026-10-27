<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Room $room): bool
    {
        return $user->isOwner() && $room->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->update($user, $room);
    }
}
