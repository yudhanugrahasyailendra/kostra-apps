<?php

namespace App\Actions\Staff;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Mail\StaffInvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Plans\PlanLimits;
use App\Support\PhoneNumber;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InviteStaff
{
    public const VALID_DAYS = 7;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<string>  $permissions
     * @return array{0: Invitation, 1: string} Undangan dan token asli (hanya ada di sini dan di tautan)
     */
    public function __invoke(User $inviter, ?string $email, ?string $phone, array $permissions = []): array
    {
        $phone = PhoneNumber::normalize($phone) ?? null;
        $email = $email !== null && $email !== '' ? mb_strtolower($email) : null;

        if ($email === null && $phone === null) {
            throw ValidationException::withMessages(['email' => 'Isi email atau nomor WhatsApp.']);
        }

        $permissions = array_values(array_intersect($permissions, Permission::values()));

        $this->ensureNotRegistered($email, $phone);

        // Batas pengguna paket (pemilik + staf + undangan yang masih berlaku); melewatinya menawarkan naik paket.
        // Tenant dari konteks permintaan ini (selalu segar), bukan relasi pengundang yang bisa tersimpan di memori.
        app(PlanLimits::class)->ensureCanAddStaff($this->context->current());

        $token = bin2hex(random_bytes(32));

        $invitation = DB::transaction(fn () => Invitation::create([
            'tenant_id' => $inviter->tenant_id,
            'invited_by' => $inviter->getKey(),
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Staff,
            'permissions' => $permissions,
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]));

        if ($email !== null) {
            // Dikirim setelah commit. Pengiriman lewat WhatsApp menunggu Fase 9.
            Mail::to($email)->send(new StaffInvitationMail(
                url: route('invitation.show', $token),
                tenantName: $inviter->tenant->name,
                inviterName: $inviter->name,
            ));
        }

        return [$invitation, $token];
    }

    private function ensureNotRegistered(?string $email, ?string $phone): void
    {
        // Email dan nomor unik di seluruh platform, jadi pengecekan ini sengaja lintas tenant.
        $taken = $this->context->withoutTenant(fn () => User::withTrashed()
            ->where(function ($query) use ($email, $phone) {
                $query->when($email, fn ($q) => $q->orWhere('email', $email))
                    ->when($phone, fn ($q) => $q->orWhere('phone', $phone));
            })->exists());

        if ($taken) {
            throw ValidationException::withMessages(['email' => 'Email atau nomor WhatsApp ini sudah terdaftar.']);
        }
    }
}
