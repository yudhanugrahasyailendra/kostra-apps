<?php

namespace App\Actions;

use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAttachment
{
    public function handle(Attachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());

        // Berkas dihapus setelah baris berhasil dihapus; sisa berkas yatim lebih aman daripada baris tanpa berkas.
        Storage::disk($attachment->disk)->delete($attachment->path);
    }
}
