<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteStaff
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Mengembalikan token asli satu kali; yang disimpan hanya hash SHA-256.
     *
     * @param  array{email?: string|null, phone?: string|null, permissions?: list<string>}  $data
     * @return array{invitation: Invitation, token: string}
     */
    public function handle(User $inviter, array $data): array
    {
        return DB::transaction(function () use ($inviter, $data) {
            $token = Str::random(48);

            $invitation = new Invitation([
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'role' => UserRole::Staff,
                'permissions' => $data['permissions'] ?? [],
                'expires_at' => now()->addDays(7),
            ]);
            $invitation->invited_by = $inviter->id;
            $invitation->token_hash = Invitation::hashToken($token);
            $invitation->save();

            $this->audit->record('invited_staff', $invitation, null, [
                'email' => $invitation->email,
                'phone' => $invitation->phone,
                'permissions' => $invitation->permissions,
            ]);

            return ['invitation' => $invitation, 'token' => $token];
        });
    }
}
