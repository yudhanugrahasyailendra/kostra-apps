<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Invitation> */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Staff,
            'permissions' => [],
            'expires_at' => now()->addDays(7),
        ];
    }

    /** Factory berjalan tanpa mass-assignment; invited_by diisi lewat ->state(). */
    public function configure(): static
    {
        return $this->afterMaking(function (Invitation $invitation): void {
            $invitation->token_hash ??= Invitation::hashToken(Str::random(48));
        });
    }
}
