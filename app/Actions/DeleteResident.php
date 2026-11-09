<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;

class DeleteResident
{
    public function __construct(private DeleteAttachment $deleteAttachment, private RevokePortalLinks $revokePortalLinks) {}

    /**
     * Hapus penghuni (soft delete) beserta lampirannya. Foto KTP tidak boleh tertinggal
     * tanpa pemilik data (retensi dan privasi).
     */
    public function handle(Resident $resident): void
    {
        DB::transaction(function () use ($resident) {
            $resident->attachments()->get()->each(fn (Attachment $attachment) => $this->deleteAttachment->handle($attachment));

            // Tautan portal penghuni yang dihapus tidak boleh tetap hidup di portal_tokens.
            $this->revokePortalLinks->handle($resident);

            $resident->delete();
        });
    }
}
