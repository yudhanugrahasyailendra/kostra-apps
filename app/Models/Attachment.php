<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model implements TenantExportable
{
    /** @use HasFactory<AttachmentFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /** Jalur berkas dan nama asli (sering memuat nama orang) tidak masuk audit_logs. */
    protected array $auditExclude = ['path', 'disk', 'original_name'];

    protected $fillable = [
        'attachable_type', 'attachable_id', 'category', 'disk', 'path', 'original_name',
        'mime', 'size', 'is_sensitive', 'uploaded_by', 'uploaded_by_resident_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => AttachmentCategory::class,
            'size' => 'integer',
            'is_sensitive' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Isi berkas apa adanya (didekripsi bila sensitif); null bila berkas tidak ada di disk. */
    public function contents(): ?string
    {
        $raw = Storage::disk($this->disk)->get($this->path);

        if ($raw === null) {
            return null;
        }

        return $this->is_sensitive ? Crypt::decryptString($raw) : $raw;
    }

    public static function exportName(): string
    {
        return 'lampiran';
    }

    /** Hanya metadata; jalur berkas dan isi tidak diekspor. */
    public static function exportColumns(): array
    {
        return ['id', 'attachable_type', 'attachable_id', 'category', 'original_name', 'mime', 'size', 'is_sensitive', 'created_at'];
    }
}
