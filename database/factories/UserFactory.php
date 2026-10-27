<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Owner,
            'permissions' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function staff(array $permissions = []): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Staff,
            'permissions' => $permissions,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'tenant_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);
    }

    public function withPhone(string $phone): static
    {
        return $this->state(fn () => ['phone' => $phone]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
