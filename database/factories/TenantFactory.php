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

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
