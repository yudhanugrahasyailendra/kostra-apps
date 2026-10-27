<?php

namespace App\Actions\Attachments;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreAttachment
{
    use ResolvesTenant;

    /** Tipe isi berkas yang diterima => ekstensi tersimpan. Diperiksa dari isi, bukan dari nama. */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private readonly DeleteAttachment $delete) {}

    /**
     * Menyimpan lampiran di disk privat. Berkas sensitif (KTP) dienkripsi sebelum ditulis.
     * Kategori tunggal (KTP, foto) menggantikan berkas lama milik induk yang sama.
     */
    public function __invoke(Model $attachable, UploadedFile $file, AttachmentCategory $category, ?User $uploader = null): Attachment
    {
        $tenant = $this->currentTenant();

        $mime = $file->getMimeType();
        $extension = self::ALLOWED[$mime] ?? null;
        $maxBytes = (int) config('kostra.attachments.max_kb') * 1024;

        if ($extension === null) {
            throw ValidationException::withMessages(['file' => 'Berkas harus berupa JPG, PNG, atau PDF.']);
        }

        if ($file->getSize() === false || $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['file' => 'Ukuran berkas maksimal '.config('kostra.attachments.max_kb') / 1024 .' MB.']);
        }

        $contents = file_get_contents($file->getRealPath());
        $sensitive = $category->isSensitive();
        $disk = (string) config('kostra.attachments.disk');
        $path = $tenant->getKey().'/'.$category->value.'/'.Str::uuid().'.'.$extension;

        // Berkas ditulis lebih dulu; bila baris database gagal, berkas dibuang lagi.
        Storage::disk($disk)->put($path, $sensitive ? Crypt::encryptString($contents) : $contents);

        try {
            $replaced = [];

            $attachment = DB::transaction(function () use ($attachable, $category, $disk, $path, $file, $mime, $sensitive, $uploader, &$replaced) {
                if ($category->isSingle()) {
                    $replaced = $attachable->attachments()->where('category', $category->value)->get()->all();
                }

                return $attachable->attachments()->create([
                    'category' => $category,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime' => $mime,
                    'size' => $file->getSize(),
                    'is_sensitive' => $sensitive,
                    'uploaded_by' => $uploader?->getKey(),
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        foreach ($replaced as $old) {
            ($this->delete)($old);
        }

        return $attachment;
    }
}
