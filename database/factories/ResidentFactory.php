<?php

namespace Database\Factories;

use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'phone' => '+62812'.fake()->unique()->numerify('########'),
            'email' => null,
            'occupation' => 'Mahasiswa',
            'consented_at' => now(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
