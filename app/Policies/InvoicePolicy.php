<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

/**
 * Tagihan adalah data keuangan: melihat dan membuat tagihan manual butuh izin `finance.view`
 * (pemilik selalu punya). Penyesuaian dan pembatalan mengubah angka uang, jadi hanya pemilik.
 * Kecocokan tenant_id diperiksa lagi sebagai lapis kedua setelah TenantScope.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasPermission(Permission::FinanceView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user) && $invoice->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function adjust(User $user, Invoice $invoice): bool
    {
        return $user->isOwner() && $invoice->tenant_id === $user->tenant_id;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->adjust($user, $invoice);
    }
}
