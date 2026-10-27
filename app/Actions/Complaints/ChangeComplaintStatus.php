<?php

namespace App\Actions\Complaints;

use App\Enums\ComplaintStatus;
use App\Events\ComplaintStatusChanged;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeComplaintStatus
{
    /**
     * Mengubah status: baru -> diproses -> selesai atau ditolak. Selesai dan ditolak menutup tiket
     * (resolved_at dan closed_at terisi) dan tidak dibuka lagi. Setiap perubahan memicu
     * ComplaintStatusChanged; pesan ke penghuni dikirim Fase 9, bukan di sini.
     */
    public function __invoke(Complaint $complaint, ComplaintStatus $to): Complaint
    {
        [$locked, $from] = DB::transaction(function () use ($complaint, $to) {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->getKey());
            $from = $locked->status;

            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages([
                    'status' => $from->isClosed()
                        ? 'Tiket sudah ditutup dan tidak bisa diubah lagi.'
                        : "Status tidak bisa berubah dari {$from->label()} ke {$to->label()}.",
                ]);
            }

            $attributes = ['status' => $to];

            if ($to->isClosed()) {
                $attributes['resolved_at'] = now();
                $attributes['closed_at'] = now();
            }

            $locked->update($attributes);

            return [$locked, $from];
        });

        event(new ComplaintStatusChanged($locked->getKey(), $locked->tenant_id, $from->value, $to->value));

        return $locked;
    }
}
