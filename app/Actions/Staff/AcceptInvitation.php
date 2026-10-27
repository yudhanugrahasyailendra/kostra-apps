<?php

namespace App\Actions\Staff;

use App\Enums\TenantStatus;
use App\Exceptions\InvalidInvitation;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @throws InvalidInvitation
     */
    public function __invoke(string $token, string $name, string $password): User
    {
        // Tenant belum diketahui sebelum token dibuka, jadi seluruh langkah berjalan lintas tenant.
        return $this->context->withoutTenant(fn () => DB::transaction(function () use ($token, $name, $password) {
            $invitation = Invitation::query()
                ->where('token_hash', Invitation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isUsable()) {
                throw new InvalidInvitation;
            }

            $taken = User::withTrashed()
                ->where(function ($query) use ($invitation) {
                    $query->when($invitation->email, fn ($q) => $q->orWhere('email', $invitation->email))
                        ->when($invitation->phone, fn ($q) => $q->orWhere('phone', $invitation->phone));
                })->exists();

            if ($taken) {
                throw new InvalidInvitation;
            }

            // Undangan bisa dibuat sebelum paket diturunkan atau akun ditangguhkan: periksa ulang saat diterima.
            $tenant = Tenant::query()->find($invitation->tenant_id);

            if ($tenant === null || ! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
                throw new InvalidInvitation;
            }

            $this->context->run($tenant, function () use ($tenant, $invitation) {
                app(PlanLimits::class)->lockTenant($tenant);

                $others = User::query()->count() + Invitation::query()->pending()->whereKeyNot($invitation->getKey())->count();
                $max = $tenant->plan->max_users;

                if ($max !== null && $others + 1 > $max) {
                    throw new InvalidInvitation;
                }
            });

            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $name,
                'email' => $invitation->email,
                'phone' => $invitation->phone,
                'password' => $password,
                'role' => $invitation->role,
                'permissions' => $invitation->permissions ?? [],
                'is_active' => true,
            ]);

            $invitation->update([
                'accepted_at' => now(),
                'accepted_user_id' => $user->getKey(),
            ]);

            return $user;
        }));
    }
}
