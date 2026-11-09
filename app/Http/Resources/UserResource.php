<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contoh bentuk data yang aman dikirim ke halaman. Hanya kolom yang dipakai
 * layar; jangan pernah mengirim model mentah (password, remember_token, dan
 * seterusnya terlihat di browser).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
