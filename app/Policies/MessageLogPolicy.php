<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessageLog;
use App\Models\User;

/** Log pesan memuat nomor penghuni dan isi pesan: butuh izin `settings.manage`. Log tidak pernah dihapus. */
class MessageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasPermission(Permission::SettingsManage);
    }

    public function resend(User $user, MessageLog $log): bool
    {
        return $this->viewAny($user) && $log->tenant_id === $user->tenant_id;
    }
}
