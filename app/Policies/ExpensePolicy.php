<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Pengeluaran adalah data keuangan: pemilik, atau staf berizin finance.view.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->viewAny($user) && $expense->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }

    /**
     * Menghapus (soft delete, tercatat di audit) hanya untuk pemilik.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $user->isOwner();
    }
}
