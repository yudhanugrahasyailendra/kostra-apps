<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

/** Pengeluaran adalah data keuangan: semua aksinya butuh izin `finance.view` (pemilik selalu punya). */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasPermission(Permission::FinanceView);
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

    public function delete(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }
}
