<?php

namespace App\Actions\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddComplaintComment
{
    /**
     * Menambah komentar. Tepat satu penulis: pengelola ($user) atau penghuni pemilik tiket ($resident).
     * Tiket yang sudah ditutup tidak menerima komentar baru; riwayatnya tetap tersimpan.
     */
    public function __invoke(Complaint $complaint, string $body, ?User $user = null, ?Resident $resident = null): ComplaintComment
    {
        if (($user === null) === ($resident === null)) {
            throw ValidationException::withMessages(['general' => 'Komentar harus punya tepat satu penulis.']);
        }

        if ($resident !== null && $resident->getKey() !== $complaint->resident_id) {
            throw ValidationException::withMessages(['general' => 'Penghuni hanya bisa berkomentar pada tiketnya sendiri.']);
        }

        $body = trim($body);

        if ($body === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => 'Komentar wajib diisi (maksimal 2000 karakter).']);
        }

        if (! $complaint->isOpen()) {
            throw ValidationException::withMessages(['body' => 'Tiket sudah ditutup dan tidak menerima komentar baru.']);
        }

        return ComplaintComment::create([
            'complaint_id' => $complaint->getKey(),
            'user_id' => $user?->getKey(),
            'resident_id' => $resident?->getKey(),
            'body' => $body,
        ]);
    }
}
