<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'floor' => $this->floor,
            'price' => $this->price,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'notes' => $this->notes,
            'property_id' => $this->property_id,
            'property_name' => $this->whenLoaded('property', fn () => $this->property->name),
            'room_type_id' => $this->room_type_id,
            'room_type_name' => $this->whenLoaded('type', fn () => $this->type?->name),
        ];
    }
}
