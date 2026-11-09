<?php

namespace App\Http\Resources;

use App\Models\Complaint;
use Illuminate\Http\Request;

/**
 * Detail tiket: daftar + deskripsi, foto (tautan bertanda tangan), dan komentar berurutan waktu.
 * Pemanggil memuat `comments.user`, `comments.resident`, dan `attachments`.
 *
 * @mixin Complaint
 */
class ComplaintDetailResource extends ComplaintResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'allowed_statuses' => array_map(
                fn ($s) => ['value' => $s->value, 'label' => $s->label()],
                $this->status->allowedNext(),
            ),
            'photos' => $this->whenLoaded('attachments', fn () => AttachmentResource::collection($this->attachments)->resolve()),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'author_name' => $comment->user?->name ?? $comment->resident?->name,
                'author_type' => $comment->user_id !== null ? 'staff' : 'resident',
                'created_at' => $comment->created_at->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
