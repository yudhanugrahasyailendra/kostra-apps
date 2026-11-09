<?php

namespace Database\Factories;

use App\Models\PortalToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * resident_id diberikan pemanggil. Token asli tidak disimpan; tes memakai PortalTokenFactory::issue()
 * untuk mendapatkan pasangan (model, token asli).
 *
 * @extends Factory<PortalToken>
 */
class PortalTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token_hash' => PortalToken::hash(PortalToken::generate()),
            'expires_at' => now()->addDays(30),
        ];
    }

    /** @return array{0: PortalToken, 1: string} */
    public static function issue(int $residentId, array $attributes = []): array
    {
        $plain = PortalToken::generate();
        $model = PortalToken::factory()->create($attributes + ['resident_id' => $residentId, 'token_hash' => PortalToken::hash($plain)]);

        return [$model, $plain];
    }
}
