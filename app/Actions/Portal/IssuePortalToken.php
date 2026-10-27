<?php

namespace App\Actions\Portal;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\User;

class IssuePortalToken
{
    use ResolvesTenant;

    /**
     * Membuat tautan portal baru untuk penghuni. Mengembalikan model dan token ASLI; token asli tidak disimpan
     * di mana pun (hanya hash-nya) dan hanya boleh dikirim lewat tautan WhatsApp. Tautan lama tetap berlaku
     * sampai kedaluwarsa atau dicabut, supaya pesan WhatsApp sebelumnya tidak mendadak mati.
     *
     * @return array{0: PortalToken, 1: string}
     */
    public function __invoke(Resident $resident, ?User $by = null, ?int $days = null): array
    {
        $this->currentTenant();

        $raw = PortalToken::generate();

        $token = PortalToken::create([
            'resident_id' => $resident->getKey(),
            'token_hash' => PortalToken::hash($raw),
            'expires_at' => now()->addDays($days ?? (int) config('kostra.portal.token_days')),
            'created_by' => $by?->getKey(),
        ]);

        return [$token, $raw];
    }
}
