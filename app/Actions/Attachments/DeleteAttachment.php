<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteAttachment
{
    /**
     * Menghapus baris lalu berkasnya (setelah commit). Lampiran keuangan tidak boleh dihapus,
     * kecuali $force dipakai oleh proses resmi seperti anonimisasi.
     */
    public function __invoke(Attachment $attachment, bool $force = false): void
    {
        if (! $force && $attachment->category->isFinancial()) {
            throw ValidationException::withMessages(['general' => 'Lampiran keuangan tidak bisa dihapus.']);
        }

        $disk = $attachment->disk;
        $path = $attachment->path;

        DB::transaction(function () use ($attachment) {
            $attachment->delete();
        });

        Storage::disk($disk)->delete($path);
    }
}
