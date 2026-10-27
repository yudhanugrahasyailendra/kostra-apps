<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Template dikelola pemilik atau staf berizin pengaturan. Baris sistem tidak pernah diubah langsung:
 * SaveMessageTemplate membuat salinan milik tenant.
 */
class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SettingsManage);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
