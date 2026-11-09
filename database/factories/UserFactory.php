<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory berjalan tanpa mass-assignment, jadi tenant_id, role, dan is_active
 * bisa diisi lewat state. Pakai ->forTenant($tenant), ->owner(), ->staff(), ->superAdmin().
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Staff,
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function owner(): static
    {
        return $this->state(['role' => UserRole::Owner]);
    }

    /** @param  list<string>  $permissions */
    public function staff(array $permissions = []): static
    {
        return $this->state(['role' => UserRole::Staff, 'permissions' => $permissions]);
    }

    public function superAdmin(): static
    {
        return $this->state(['role' => UserRole::SuperAdmin]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
