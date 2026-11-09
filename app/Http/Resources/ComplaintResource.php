<?php

namespace App\Http\Resources;

use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tiket di daftar. Relasi `room`, `property`, `resident`, dan `assignee` dimuat pemanggil.
 * Hanya nama penghuni yang dikirim; telepon dan data pribadi lain tidak dipakai layar ini.
 *
 * @mixin Complaint
 */
class ComplaintResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn () => $this->room->number),
            'property_name' => $this->whenLoaded('property', fn () => $this->property->name),
            'resident_name' => $this->whenLoaded('resident', fn () => $this->resident->name),
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'is_stale' => $this->isStale(),
            'created_at' => $this->created_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
