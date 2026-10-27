<?php

namespace App\Actions\Complaints;

use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignComplaint
{
    /**
     * Menetapkan penanggung jawab (pemilik atau staf aktif di tenant yang sama), atau null untuk melepas.
     * Pengguna dicari lewat model ber-scope tenant, jadi id milik tenant lain gagal sebagai tidak ditemukan.
     */
    public function __invoke(Complaint $complaint, ?int $userId): Complaint
    {
        $assignee = null;

        if ($userId !== null) {
            $assignee = User::query()
                ->whereKey($userId)
                ->whereIn('role', [UserRole::Owner->value, UserRole::Staff->value])
                ->where('is_active', true)
                ->first();

            if ($assignee === null) {
                throw ValidationException::withMessages(['assigned_to' => 'Penanggung jawab harus pemilik atau staf aktif di kos ini.']);
            }
        }

        return DB::transaction(function () use ($complaint, $assignee) {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['assigned_to' => 'Tiket sudah ditutup.']);
            }

            $locked->update(['assigned_to' => $assignee?->getKey()]);

            return $locked;
        });
    }
}
