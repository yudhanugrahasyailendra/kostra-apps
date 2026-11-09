<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    /**
     * Mencari undangan dari token mentah. Pengunjung belum punya tenant, jadi
     * pencarian ini sengaja lintas tenant dan hanya lewat hash token.
     */
    public function find(string $token): ?Invitation
    {
        // Alasan unscoped: tautan undangan dibuka sebelum ada sesi dan tenant.
        return TenantContext::instance()->unscoped(
            fn () => Invitation::query()->where('token_hash', Invitation::hashToken($token))->first()
        );
    }

    /** Undangan hanya berlaku bila belum dipakai, belum kedaluwarsa, dan tenant boleh menambah pengguna. */
    public function isAcceptable(Invitation $invitation): bool
    {
        if (! $invitation->isUsable()) {
            return false;
        }

        return Tenant::query()->find($invitation->tenant_id)?->canAddUsers() ?? false;
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, password: string}  $data
     */
    public function handle(string $token, array $data): User
    {
        $invitation = $this->find($token);

        if ($invitation === null || ! $this->isAcceptable($invitation)) {
            throw ValidationException::withMessages(['token' => 'Undangan tidak berlaku, sudah dipakai, atau kedaluwarsa.']);
        }

        $tenant = Tenant::query()->findOrFail($invitation->tenant_id);

        return TenantContext::instance()->runAs($tenant, function () use ($invitation, $data) {
            return DB::transaction(function () use ($invitation, $data) {
                // Kunci baris agar dua penerimaan bersamaan tidak sama-sama lolos.
                $locked = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isUsable()) {
                    throw ValidationException::withMessages(['token' => 'Undangan tidak berlaku, sudah dipakai, atau kedaluwarsa.']);
                }

                $user = new User([
                    'name' => $data['name'],
                    // Tujuan yang disebut undangan tidak boleh diganti penerima.
                    'email' => $locked->email ?? $data['email'] ?? null,
                    'phone' => $locked->phone ?? $data['phone'] ?? null,
                    'password' => $data['password'],
                    'permissions' => $locked->permissions,
                ]);
                $user->role = $locked->role;
                $user->save();

                $locked->accepted_at = now();
                $locked->accepted_user_id = $user->id;
                $locked->save();

                return $user;
            });
        });
    }
}
