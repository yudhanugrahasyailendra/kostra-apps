<?php

namespace Tests\Feature\Properties;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class RoomRulesTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    public function test_bulk_creates_twenty_rooms_in_one_step(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'from' => 101, 'to' => 120, 'floor' => 1, 'price' => '1.400.000',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rooms = $this->asTenant($tenant, fn () => Room::orderBy('id')->get());
        $this->assertCount(20, $rooms);
        $this->assertSame('101', $rooms->first()->number);
        $this->assertSame('120', $rooms->last()->number);
        $this->assertTrue($rooms->every(fn ($r) => $r->status === RoomStatus::Vacant && $r->price === 1_400_000));
    }

    public function test_bulk_with_prefix_uses_type_price_by_default(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);
        $type = RoomType::factory()->for($tenant)->create(['base_price' => 1_750_000]);

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'prefix' => 'A-', 'from' => 1, 'to' => 3, 'room_type_id' => $type->id,
        ])->assertSessionHasNoErrors();

        $rooms = $this->asTenant($tenant, fn () => Room::orderBy('id')->get());
        $this->assertSame(['A-1', 'A-2', 'A-3'], $rooms->pluck('number')->all());
        $this->assertSame([1_750_000], $rooms->pluck('price')->unique()->values()->all());
    }

    public function test_bulk_is_all_or_nothing_when_a_number_collides(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);
        Room::factory()->for($tenant)->for($property)->create(['number' => '105']);

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'from' => 101, 'to' => 110, 'price' => 1000000,
        ])->assertSessionHasErrors('from');

        $this->assertSame(1, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_bulk_rejects_more_than_one_hundred_rooms_and_inverted_ranges(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'from' => 1, 'to' => 101, 'price' => 1000000,
        ])->assertSessionHasErrors('to');

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'from' => 10, 'to' => 5, 'price' => 1000000,
        ])->assertSessionHasErrors('to');

        $this->assertSame(0, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_room_number_is_unique_per_property_only(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $a = $this->propertyFor($tenant);
        $b = $this->propertyFor($tenant);

        $store = fn ($property) => $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '101', 'price' => 1000000,
        ]);

        $store($a)->assertSessionHasNoErrors();
        $store($b)->assertSessionHasNoErrors();
        $store($a)->assertSessionHasErrors('number');

        $this->assertSame(2, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_number_of_a_deleted_room_can_be_reused(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->create(['number' => '101']);

        $this->actingAs($owner)->delete(route('owner.rooms.destroy', $room))->assertRedirect();
        $this->assertSoftDeleted($room);

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '101', 'price' => 1000000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->asTenant($tenant, fn () => Room::where('number', '101')->count()));
    }

    public function test_occupied_status_cannot_be_set_manually(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '101', 'price' => 1000000, 'status' => 'occupied',
        ])->assertSessionHasErrors('status');

        $room = Room::factory()->for($tenant)->for($property)->create();

        $this->actingAs($owner)->put(route('owner.rooms.update', $room), [
            'number' => $room->number, 'price' => 1000000, 'status' => 'occupied',
        ])->assertSessionHasErrors('status');

        $this->assertSame(RoomStatus::Vacant, $this->asTenant($tenant, fn () => Room::findOrFail($room->id)->status));
    }

    public function test_occupied_room_cannot_be_moved_to_maintenance_or_deleted(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->occupied()->create();

        $this->actingAs($owner)->put(route('owner.rooms.update', $room), [
            'number' => $room->number, 'price' => 1000000, 'status' => 'maintenance',
        ])->assertSessionHasErrors('status');

        $this->actingAs($owner)->delete(route('owner.rooms.destroy', $room))->assertSessionHasErrors('general');

        $fresh = $this->asTenant($tenant, fn () => Room::findOrFail($room->id));
        $this->assertSame(RoomStatus::Occupied, $fresh->status);

        // Mengubah harga kamar terisi tetap boleh (tanpa menyentuh status).
        $this->actingAs($owner)->put(route('owner.rooms.update', $room), [
            'number' => $room->number, 'price' => '1.600.000',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1_600_000, $this->asTenant($tenant, fn () => Room::findOrFail($room->id)->price));
    }

    public function test_room_can_go_to_maintenance_and_back(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->create();

        $this->actingAs($owner)->put(route('owner.rooms.update', $room), [
            'number' => $room->number, 'price' => $room->price, 'status' => 'maintenance',
        ])->assertSessionHasNoErrors();

        $this->assertSame(RoomStatus::Maintenance, $this->asTenant($tenant, fn () => Room::findOrFail($room->id)->status));
    }

    public function test_type_of_another_property_is_refused(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $a = $this->propertyFor($tenant);
        $b = $this->propertyFor($tenant);
        $typeOfB = RoomType::factory()->for($tenant)->for($b)->create();

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $a->id, 'number' => '101', 'room_type_id' => $typeOfB->id,
        ])->assertSessionHasErrors('room_type_id');
    }

    public function test_price_is_required_without_a_type_and_stays_an_integer(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '101',
        ])->assertSessionHasErrors('price');

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '101', 'price' => '1.500,50',
        ])->assertSessionHasErrors('price');
    }
}
