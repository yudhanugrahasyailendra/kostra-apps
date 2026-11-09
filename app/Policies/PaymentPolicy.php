<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

/**
 * Pembayaran adalah data keuangan: melihat, mencatat, dan membatalkan butuh izin `finance.view`
 * (pemilik selalu punya). Staf tanpa izin keuangan tidak melihat atau menyentuhnya sama sekali.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasPermission(Permission::FinanceView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user) && $payment->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
