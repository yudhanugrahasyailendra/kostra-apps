<?php

namespace App\Http\Resources\Portal;

use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Komplain untuk penghuni pengaju. Tanpa nama penanggung jawab internal (hanya "ditangani"), tanpa id
 * pengguna, dan komentar dengan nama pengirim seperlunya. `comments` dimuat pemanggil di halaman detail.
 *
 * @mixin Complaint
 */
class PortalComplaintResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category_label' => $this->category->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'is_closed' => $this->status->isClosed(),
            'created_at' => $this->created_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'from_resident' => $comment->resident_id !== null,
                'author_name' => $comment->resident_id !== null ? 'Anda' : 'Pengelola',
                'created_at' => $comment->created_at->toIso8601String(),
            ])->values()),
        ];
    }
}
