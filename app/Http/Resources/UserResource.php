<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk pengguna yang aman dikirim ke halaman. Hanya kolom yang dipakai
 * layar; jangan pernah mengirim model mentah (password, remember_token, dan
 * seterusnya terlihat di browser). Kirim ke Inertia dengan ->resolve().
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
            'phone' => $this->phone,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'permissions' => $this->permissions ?? [],
        ];
    }
}
