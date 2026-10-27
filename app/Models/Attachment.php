<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

class Attachment extends Model
{
    use Auditable, BelongsToTenant;

    public const URL_TTL_MINUTES = 5;

    protected $fillable = [
        'tenant_id',
        'attachable_type',
        'attachable_id',
        'category',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'is_sensitive',
        'uploaded_by',
        'uploaded_by_resident_id',
    ];

    /** @var list<string> Nama berkas asli sering memuat nama orang; jalur disimpan tapi tidak perlu di audit. */
    protected array $auditExtraExclude = ['original_name', 'path'];

    protected function casts(): array
    {
        return [
            'category' => AttachmentCategory::class,
            'is_sensitive' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Tautan bertanda tangan berumur pendek. Penyajian tetap melewati login dan Policy.
     */
    public function temporaryUrl(): string
    {
        return URL::temporarySignedRoute(
            'owner.attachments.show',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['attachment' => $this->getKey()],
        );
    }
}
