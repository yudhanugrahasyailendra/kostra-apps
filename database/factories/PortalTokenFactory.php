<?php

namespace Database\Factories;

use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Berikan tenant dan penghuni yang konsisten: PortalToken::factory()->for($tenant)->for($resident).
 * Token asli tidak disimpan di model; tes yang butuh tautan memakai ->forToken($raw).
 *
 * @extends Factory<PortalToken>
 */
class PortalTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'resident_id' => Resident::factory(),
            'token_hash' => PortalToken::hash(PortalToken::generate()),
            'expires_at' => now()->addDays(60),
        ];
    }

    public function forToken(string $raw): static
    {
        return $this->state(fn () => ['token_hash' => PortalToken::hash($raw)]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()->subMinute()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
