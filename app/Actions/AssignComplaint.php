<?php

namespace App\Actions;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignComplaint
{
    /**
     * Tetapkan penanggung jawab; null melepas penugasan. Hanya pengguna aktif dari tenant yang sama.
     * Tiket yang sudah ditutup tidak ditugaskan ulang.
     */
    public function handle(Complaint $complaint, ?User $assignee): Complaint
    {
        if ($complaint->status->isClosed()) {
            throw ValidationException::withMessages(['assigned_to' => 'Tiket yang sudah ditutup tidak dapat ditugaskan ulang.']);
        }

        if ($assignee !== null && ($assignee->tenant_id === null || $assignee->tenant_id !== $complaint->tenant_id || ! $assignee->is_active)) {
            throw ValidationException::withMessages(['assigned_to' => 'Penanggung jawab harus pengguna aktif di kos ini.']);
        }

        $complaint->update(['assigned_to' => $assignee?->id]);

        return $complaint;
    }
}
