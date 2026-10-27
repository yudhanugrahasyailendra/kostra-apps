<?php

namespace Tests\Concerns;

use App\Models\Plan;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;

trait CreatesTenantFixtures
{
    /**
     * @return array{0: Tenant, 1: User}
     */
    protected function tenantWithOwner(string $plan = 'free'): array
    {
        $this->seedPlans();

        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('key', $plan)->value('id')]);
        $owner = User::factory()->for($tenant)->create();

        return [$tenant, $owner];
    }

    protected function propertyFor(Tenant $tenant, array $attributes = []): Property
    {
        return Property::factory()->for($tenant)->create($attributes);
    }
}
