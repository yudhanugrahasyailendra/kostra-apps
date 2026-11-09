<?php

namespace App\Actions;

use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AddComplaintComment
{
    /**
     * Tambah komentar. Penulis tepat satu: pengguna (pemilik/staf) atau penghuni (dipakai portal, Fase 10).
     * Penulis harus berasal dari tenant yang sama dengan tiket.
     */
    public function handle(Complaint $complaint, string $body, ?User $user = null, ?Resident $resident = null): ComplaintComment
    {
        if (($user === null) === ($resident === null)) {
            throw new InvalidArgumentException('Komentar harus punya tepat satu penulis: pengguna atau penghuni.');
        }

        $author = $user ?? $resident;

        if ($author->tenant_id !== $complaint->tenant_id) {
            throw new InvalidArgumentException('Penulis komentar bukan bagian dari kos pemilik tiket.');
        }

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Komentar tidak boleh kosong.']);
        }

        return ComplaintComment::create([
            'complaint_id' => $complaint->id,
            'user_id' => $user?->id,
            'resident_id' => $resident?->id,
            'body' => $body,
        ]);
    }
}
