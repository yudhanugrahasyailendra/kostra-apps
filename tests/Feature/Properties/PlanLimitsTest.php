<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use App\Models\Room;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    public function test_free_plan_cannot_create_a_second_property(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Kos Kedua'])
            ->assertSessionHasErrors('plan_limit');

        $this->assertSame(1, $this->asTenant($tenant, fn () => Property::count()));

        $this->actingAs($owner)->followingRedirects()->get(route('owner.properties.index'));
    }

    public function test_free_plan_cannot_create_the_eleventh_room(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        Room::factory()->count(10)->for($tenant)->for($property)->create();

        $response = $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => 'X1', 'price' => 1000000,
        ]);

        $response->assertSessionHasErrors('plan_limit');
        $this->assertStringContainsString('Naik paket', session('errors')->first('plan_limit'));
        $this->assertSame(10, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_bulk_creation_respects_the_room_limit_as_a_whole(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        Room::factory()->count(8)->for($tenant)->for($property)->create();

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'from' => 201, 'to' => 205, 'price' => 1000000,
        ])->assertSessionHasErrors('plan_limit');

        $this->assertSame(8, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_deleted_rooms_do_not_count_towards_the_limit(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $rooms = Room::factory()->count(10)->for($tenant)->for($property)->create();
        $rooms->first()->delete();

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => 'BARU', 'price' => 1000000,
        ])->assertSessionHasNoErrors();
    }

    public function test_paid_plan_allows_more_and_unlimited_plan_has_no_limit(): void
    {
        [$tenant] = $this->tenantWithOwner('pro');
        $limits = app(PlanLimits::class);

        $remaining = app(TenantContext::class)->run($tenant, fn () => [
            $limits->remainingProperties($tenant),
            $limits->remainingRooms($tenant),
        ]);

        $this->assertSame([3, 100], $remaining);

        [$business] = $this->tenantWithOwner('business');
        $this->assertSame([null, null], app(TenantContext::class)->run($business, fn () => [
            $limits->remainingProperties($business),
            $limits->remainingRooms($business),
        ]));
    }

    public function test_plan_limits_refuse_to_run_outside_the_checked_tenants_context(): void
    {
        [$tenant] = $this->tenantWithOwner();

        $this->expectException(\LogicException::class);

        app(PlanLimits::class)->remainingRooms($tenant);
    }
}
