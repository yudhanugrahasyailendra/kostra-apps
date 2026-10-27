<?php

namespace App\Policies;

use App\Enums\AttachmentCategory;
use App\Enums\Permission;
use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Mengunggah ke penghuni: berkas sensitif butuh izin documents.view (pemilik selalu punya).
     */
    public function upload(User $user, AttachmentCategory $category): bool
    {
        if (! ($user->isOwner() || $user->isStaff())) {
            return false;
        }

        return $category->isSensitive() ? $user->hasPermission(Permission::DocumentsView) : true;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        if ($attachment->tenant_id !== $user->tenant_id || ! ($user->isOwner() || $user->isStaff())) {
            return false;
        }

        // Nota pengeluaran adalah data keuangan: hanya yang boleh melihat keuangan.
        if ($attachment->category === AttachmentCategory::ExpenseReceipt && ! $user->hasPermission(Permission::FinanceView)) {
            return false;
        }

        return $attachment->is_sensitive ? $user->hasPermission(Permission::DocumentsView) : true;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }
}
