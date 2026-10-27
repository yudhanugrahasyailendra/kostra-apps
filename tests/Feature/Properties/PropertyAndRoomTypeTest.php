<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class PropertyAndRoomTypeTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    public function test_owner_creates_updates_and_deletes_an_empty_property(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.properties.store'), [
            'name' => 'Kos Melati', 'city' => 'Makassar', 'floors' => 2,
        ])->assertRedirect(route('owner.properties.index'));

        $property = $this->asTenant($tenant, fn () => Property::firstOrFail());
        $this->assertSame($tenant->id, $property->tenant_id);

        $this->actingAs($owner)->put(route('owner.properties.update', $property), ['name' => 'Kos Mawar'])
            ->assertRedirect(route('owner.properties.index'));
        $this->assertSame('Kos Mawar', $this->asTenant($tenant, fn () => Property::firstOrFail()->name));

        $this->actingAs($owner)->delete(route('owner.properties.destroy', $property))
            ->assertRedirect(route('owner.properties.index'));
        $this->assertSame(0, $this->asTenant($tenant, fn () => Property::count()));
    }

    public function test_property_with_rooms_cannot_be_deleted(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        Room::factory()->for($tenant)->for($property)->create();

        $this->actingAs($owner)->delete(route('owner.properties.destroy', $property))
            ->assertSessionHasErrors('general');

        $this->assertSame(1, $this->asTenant($tenant, fn () => Property::count()));
    }

    public function test_staff_can_view_but_not_change_properties_rooms_and_types(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $staff = User::factory()->for($tenant)->staff()->create();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->create();
        $type = RoomType::factory()->for($tenant)->create();

        $this->actingAs($staff)->get(route('owner.properties.index'))->assertOk();
        $this->actingAs($staff)->get(route('owner.rooms.index'))->assertOk();
        $this->actingAs($staff)->get(route('owner.room-types.index'))->assertOk();

        $this->actingAs($staff)->post(route('owner.properties.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->put(route('owner.rooms.update', $room), ['number' => '1', 'price' => 1000])->assertForbidden();
        $this->actingAs($staff)->delete(route('owner.rooms.destroy', $room))->assertForbidden();
        $this->actingAs($staff)->post(route('owner.room-types.store'), ['name' => 'T', 'base_price' => 1000])->assertForbidden();
        $this->actingAs($staff)->delete(route('owner.room-types.destroy', $type))->assertForbidden();
        $this->actingAs($staff)->post(route('owner.rooms.bulk'), [])->assertForbidden();
    }

    public function test_room_type_price_input_accepts_rupiah_formatting_and_is_stored_as_integer(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.room-types.store'), [
            'name' => 'Deluxe AC', 'base_price' => 'Rp 1.850.000', 'facilities' => 'AC, Kamar mandi dalam',
        ])->assertRedirect(route('owner.room-types.index'));

        $type = $this->asTenant($tenant, fn () => RoomType::firstOrFail());
        $this->assertSame(1_850_000, $type->base_price);
        $this->assertSame(['AC', 'Kamar mandi dalam'], $type->facilities);
    }

    public function test_room_type_names_are_unique_per_property_scope(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $a = $this->propertyFor($tenant);
        $b = $this->propertyFor($tenant);

        $post = fn (array $data) => $this->actingAs($owner)->post(route('owner.room-types.store'), $data + ['base_price' => 1000000]);

        $post(['name' => 'Standard', 'property_id' => $a->id])->assertSessionHasNoErrors();
        $post(['name' => 'Standard', 'property_id' => $b->id])->assertSessionHasNoErrors();
        $post(['name' => 'Standard', 'property_id' => $a->id])->assertSessionHasErrors('name');

        $post(['name' => 'Umum'])->assertSessionHasNoErrors();
        $post(['name' => 'Umum'])->assertSessionHasErrors('name');
    }

    public function test_changing_type_base_price_does_not_change_existing_room_prices(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $type = RoomType::factory()->for($tenant)->create(['base_price' => 1_500_000]);

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '101', 'room_type_id' => $type->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->put(route('owner.room-types.update', $type), [
            'name' => $type->name, 'base_price' => '2.000.000',
        ])->assertRedirect(route('owner.room-types.index'));

        $this->assertSame(2_000_000, $this->asTenant($tenant, fn () => RoomType::firstOrFail()->base_price));
        $this->assertSame(1_500_000, $this->asTenant($tenant, fn () => Room::firstOrFail()->price));
    }

    public function test_used_room_type_cannot_be_deleted_but_unused_can(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $used = RoomType::factory()->for($tenant)->create();
        $unused = RoomType::factory()->for($tenant)->create();
        Room::factory()->for($tenant)->for($property)->create(['room_type_id' => $used->id]);

        $this->actingAs($owner)->delete(route('owner.room-types.destroy', $used))->assertSessionHasErrors('general');
        $this->actingAs($owner)->delete(route('owner.room-types.destroy', $unused))->assertRedirect(route('owner.room-types.index'));

        $this->assertSame(1, $this->asTenant($tenant, fn () => RoomType::count()));
    }
}
