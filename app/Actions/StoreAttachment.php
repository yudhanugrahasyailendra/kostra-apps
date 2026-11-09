<?php

namespace App\Actions;

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\Resident;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMismatchException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreAttachment
{
    public const DISK = 'attachments';

    /**
     * Simpan berkas ke disk privat. Berkas sensitif (foto KTP) dienkripsi dengan APP_KEY sebelum
     * ditulis, jadi isi di disk bukan byte gambar asli. Nama berkas acak, tanpa nama asli
     * dan tanpa ekstensi, di folder per tenant. Tipe diambil dari isi berkas, bukan dari klien.
     */
    public function handle(Model $attachable, UploadedFile $file, AttachmentCategory $category, ?User $uploader = null, ?Resident $residentUploader = null): Attachment
    {
        // Induk harus milik tenant aktif; hook creating hanya menjaga tenant_id lampiran itu sendiri.
        if ((int) $attachable->getAttribute('tenant_id') !== TenantContext::instance()->id()) {
            throw new TenantMismatchException('Induk lampiran bukan milik tenant aktif.');
        }

        $sensitive = $category->isSensitive();
        $contents = (string) file_get_contents($file->getRealPath());
        $disk = Storage::disk(self::DISK);
        $path = TenantContext::instance()->id().'/'.Str::uuid()->toString().'.bin';

        $disk->put($path, $sensitive ? Crypt::encryptString($contents) : $contents);

        try {
            return DB::transaction(fn () => Attachment::create([
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
                'category' => $category,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime' => (string) $file->getMimeType(),
                'size' => strlen($contents),
                'is_sensitive' => $sensitive,
                'uploaded_by' => $uploader?->id,
                // Terisi bila penghuni mengunggah dari portal; kolom pengguna di atas null pada kasus itu.
                'uploaded_by_resident_id' => $residentUploader?->id,
            ]));
        } catch (Throwable $e) {
            $disk->delete($path);

            throw $e;
        }
    }
}
