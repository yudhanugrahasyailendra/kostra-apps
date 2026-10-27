<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isStaff();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user) && $invoice->tenant_id === $user->tenant_id;
    }

    /**
     * Tagihan manual/insidental adalah pekerjaan harian: pemilik dan staf.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Penyesuaian dan pembatalan mengubah uang yang ditagih: hanya pemilik.
     */
    public function adjust(User $user, Invoice $invoice): bool
    {
        return $user->isOwner() && $invoice->tenant_id === $user->tenant_id;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->adjust($user, $invoice);
    }
}
