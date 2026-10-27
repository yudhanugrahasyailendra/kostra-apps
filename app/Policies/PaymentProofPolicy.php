<?php

namespace App\Policies;

use App\Models\PaymentProof;
use App\Models\User;

class PaymentProofPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function view(User $user, PaymentProof $proof): bool
    {
        return $this->viewAny($user) && $proof->tenant_id === $user->tenant_id;
    }

    public function submit(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function review(User $user, PaymentProof $proof): bool
    {
        return $this->view($user, $proof);
    }
}
