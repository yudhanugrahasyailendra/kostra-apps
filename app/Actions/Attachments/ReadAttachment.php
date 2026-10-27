<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class ReadAttachment
{
    /**
     * Isi asli berkas (didekripsi bila sensitif). Pemeriksaan izin dilakukan pemanggil.
     */
    public function __invoke(Attachment $attachment): string
    {
        $raw = Storage::disk($attachment->disk)->get($attachment->path);

        return $attachment->is_sensitive ? Crypt::decryptString($raw) : $raw;
    }
}
