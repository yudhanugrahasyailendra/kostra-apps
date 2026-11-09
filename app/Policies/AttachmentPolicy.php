<?php

namespace App\Policies;

use App\Enums\AttachmentCategory;
use App\Enums\Permission;
use App\Models\Attachment;
use App\Models\User;

/**
 * Berkas biasa terbuka bagi pengguna tenant yang sama. Berkas sensitif (foto KTP) hanya
 * untuk pemilik dan staf dengan izin `files.sensitive` (PNG-02).
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->tenant_id !== null
            && $attachment->tenant_id === $user->tenant_id
            && $this->mayHandle($user, $attachment->is_sensitive);
    }

    public function create(User $user, AttachmentCategory $category): bool
    {
        return $user->tenant_id !== null && $this->mayHandle($user, $category->isSensitive());
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }

    private function mayHandle(User $user, bool $sensitive): bool
    {
        return ! $sensitive || $user->hasPermission(Permission::SensitiveFiles);
    }
}
