<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user) && $payment->tenant_id === $user->tenant_id;
    }

    /**
     * Mencatat pembayaran adalah pekerjaan harian: pemilik dan staf.
     */
    public function record(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Membatalkan pembayaran mengembalikan tagihan menjadi belum dibayar: pemilik, atau staf berizin payments.void.
     */
    public function void(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment) && $user->hasPermission(Permission::PaymentsVoid);
    }
}
