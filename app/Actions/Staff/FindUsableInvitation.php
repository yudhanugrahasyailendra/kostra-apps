<?php

namespace App\Actions\Staff;

use App\Models\Invitation;
use App\Tenancy\TenantContext;

class FindUsableInvitation
{
    public function __construct(private readonly TenantContext $context) {}

    public function __invoke(string $token): ?Invitation
    {
        // Pemilik token belum login, jadi tenant belum diketahui: pencarian lintas tenant lewat hash token.
        $invitation = $this->context->withoutTenant(fn () => Invitation::query()
            ->where('token_hash', Invitation::hashToken($token))
            ->first());

        return $invitation?->isUsable() ? $invitation : null;
    }
}
