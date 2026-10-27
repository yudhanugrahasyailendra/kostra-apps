<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Kos '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'plan_id' => fn () => Plan::query()->where('key', 'free')->value('id')
                ?? Plan::factory()->free()->create()->getKey(),
            'status' => TenantStatus::Active,
            'timezone' => config('kostra.default_timezone'),
        ];
    }

    /**
     * Tenant di paket dengan batas longgar (pemilik + beberapa staf), untuk tes yang mengundang staf.
     */
    public function withStaffRoom(int $users = 5): static
    {
        return $this->state(fn () => [
            'plan_id' => Plan::factory()->create(['max_users' => $users, 'max_rooms' => 100, 'max_properties' => 3])->getKey(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
