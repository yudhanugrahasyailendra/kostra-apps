<?php

namespace App\Http\Resources;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomType */
class RoomTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'property_name' => $this->whenLoaded('property', fn () => $this->property?->name),
            'name' => $this->name,
            'base_price' => $this->base_price,
            'size_m2' => $this->size_m2 !== null ? (float) $this->size_m2 : null,
            'facilities' => $this->facilities ?? [],
            'description' => $this->description,
            'is_active' => $this->is_active,
            'rooms_count' => $this->whenCounted('rooms'),
        ];
    }
}
