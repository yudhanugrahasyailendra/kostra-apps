<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessageTemplate;
use App\Models\User;

/**
 * Template dan log pesan memuat nomor telepon dan teks ke penghuni, jadi butuh izin `settings.manage`
 * (pemilik selalu punya). Template sistem (tenant_id null) tidak pernah bisa diubah pengguna.
 */
class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasPermission(Permission::SettingsManage);
    }

    public function update(User $user, ?MessageTemplate $template = null): bool
    {
        return $this->viewAny($user) && ($template === null || $template->tenant_id === $user->tenant_id);
    }
}
