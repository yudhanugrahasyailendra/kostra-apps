<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paket untuk halaman Paket Langganan. Harga integer rupiah.
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'max_properties' => $this->max_properties,
            'max_rooms' => $this->max_rooms,
            'max_users' => $this->max_users,
            'monthly_message_quota' => $this->monthly_message_quota,
            'features' => $this->features ?? [],
            'is_free' => $this->isFree(),
        ];
    }
}
