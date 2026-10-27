<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessageLog;
use App\Models\User;

/**
 * Log pesan memuat nomor telepon dan isi pesan penghuni, jadi hanya untuk pemilik atau staf berizin pengaturan.
 */
class MessageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SettingsManage);
    }

    public function resend(User $user, MessageLog $log): bool
    {
        return $this->viewAny($user) && (int) $log->tenant_id === (int) $user->tenant_id;
    }
}
