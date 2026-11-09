<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tidak pernah memuat token_hash.
 *
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'permissions' => $this->permissions ?? [],
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
