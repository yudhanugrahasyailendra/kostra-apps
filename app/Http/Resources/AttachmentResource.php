<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Hanya metadata dan tautan bertanda tangan berumur pendek. Jalur berkas, disk, dan
 * tenant_id tidak pernah dikirim ke browser. Pemanggil hanya boleh memasukkan lampiran
 * yang lolos Policy `view`.
 *
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    public const URL_TTL_MINUTES = 5;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'is_sensitive' => $this->is_sensitive,
            'created_at' => $this->created_at?->toIso8601String(),
            'url' => URL::temporarySignedRoute('owner.attachments.show', now()->addMinutes(self::URL_TTL_MINUTES), ['attachment' => $this->id], absolute: false),
        ];
    }
}
