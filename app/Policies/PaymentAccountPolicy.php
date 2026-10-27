<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentAccount;
use App\Models\User;

class PaymentAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function manageAny(User $user): bool
    {
        return $user->hasPermission(Permission::SettingsManage);
    }

    /**
     * Rekening dikelola pemilik atau staf berizin pengaturan.
     */
    public function manage(User $user, ?PaymentAccount $account = null): bool
    {
        return $user->hasPermission(Permission::SettingsManage)
            && ($account === null || $account->tenant_id === $user->tenant_id);
    }
}
