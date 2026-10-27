<?php

namespace App\Actions\Staff;

use App\Enums\Permission;
use App\Models\User;

class UpdateStaffPermissions
{
    /**
     * @param  list<string>  $permissions
     */
    public function __invoke(User $staff, array $permissions): User
    {
        $staff->update([
            'permissions' => array_values(array_intersect($permissions, Permission::values())),
        ]);

        return $staff;
    }
}
