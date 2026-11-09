<?php

namespace App\Actions;

use App\Enums\ComplaintStatus;
use App\Events\ComplaintStatusChanged;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeComplaintStatus
{
    /**
     * Ubah status sesuai alur: baru, diproses, lalu selesai atau ditolak (baru boleh langsung ke selesai/ditolak).
     * Selesai dan ditolak menutup tiket (resolved_at dan closed_at) dan tidak bisa dibuka lagi.
     * Event dikirim setelah transaksi berhasil, satu kali per perubahan.
     */
    public function handle(Complaint $complaint, ComplaintStatus $to): Complaint
    {
        $from = DB::transaction(function () use ($complaint, $to) {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => $from->isClosed()
                        ? 'Tiket yang sudah ditutup tidak dapat diubah statusnya.'
                        : 'Status tidak dapat diubah dari '.mb_strtolower($from->label()).' ke '.mb_strtolower($to->label()).'.',
                ]);
            }

            $locked->status = $to;

            if ($to->isClosed()) {
                $locked->resolved_at = now();
                $locked->closed_at = now();
            }

            $locked->save();
            $complaint->setRawAttributes($locked->getAttributes(), true);

            return $from;
        });

        ComplaintStatusChanged::dispatch($complaint, $from, $to);

        return $complaint;
    }
}
