<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class RoomTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private Property $propertyA;

    private Property $propertyB;

    private Room $roomB;

    private RoomType $typeB;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenantA, $this->ownerA] = $this->tenantWithOwner('pro');
        $this->tenantB = Tenant::factory()->create();

        $this->propertyA = $this->propertyFor($this->tenantA);
        $this->propertyB = $this->propertyFor($this->tenantB);
        $this->roomB = Room::factory()->for($this->tenantB)->for($this->propertyB)->create(['number' => 'B-101']);
        $this->typeB = RoomType::factory()->for($this->tenantB)->create();
    }

    public function test_models_do_not_cross_tenants(): void
    {
        $this->asTenant($this->tenantA, function () {
            $this->assertNull(Property::find($this->propertyB->id));
            $this->assertNull(Room::find($this->roomB->id));
            $this->assertNull(RoomType::find($this->typeB->id));
            $this->assertSame(0, Room::count());
        });
    }

    public function test_urls_of_another_tenants_records_are_404(): void
    {
        $a = $this->actingAs($this->ownerA);

        $a->get(route('owner.properties.edit', $this->propertyB))->assertNotFound();
        $a->put(route('owner.properties.update', $this->propertyB), ['name' => 'Diretas'])->assertNotFound();
        $a->delete(route('owner.properties.destroy', $this->propertyB))->assertNotFound();

        $a->get(route('owner.rooms.edit', $this->roomB))->assertNotFound();
        $a->put(route('owner.rooms.update', $this->roomB), ['number' => 'X', 'price' => 1000])->assertNotFound();
        $a->delete(route('owner.rooms.destroy', $this->roomB))->assertNotFound();

        $a->get(route('owner.room-types.edit', $this->typeB))->assertNotFound();
        $a->put(route('owner.room-types.update', $this->typeB), ['name' => 'X', 'base_price' => 1000])->assertNotFound();
        $a->delete(route('owner.room-types.destroy', $this->typeB))->assertNotFound();

        $this->assertSame('B-101', $this->withoutTenant(fn () => Room::findOrFail($this->roomB->id)->number));
        $this->assertNotNull($this->withoutTenant(fn () => Property::find($this->propertyB->id)));
    }

    public function test_room_list_and_dashboard_only_show_own_rooms(): void
    {
        Room::factory()->for($this->tenantA)->for($this->propertyA)->create(['number' => 'A-101']);

        foreach ([route('owner.rooms.index'), route('owner.dashboard')] as $url) {
            $this->actingAs($this->ownerA)->get($url)->assertOk()->assertSee('A-101')->assertDontSee('B-101');
        }
    }

    public function test_cannot_attach_records_to_another_tenants_property_or_type(): void
    {
        $a = $this->actingAs($this->ownerA);

        $a->post(route('owner.rooms.store'), ['property_id' => $this->propertyB->id, 'number' => '1', 'price' => 1000])
            ->assertSessionHasErrors('property_id');

        $a->post(route('owner.rooms.store'), ['property_id' => $this->propertyA->id, 'number' => '1', 'price' => 1000, 'room_type_id' => $this->typeB->id])
            ->assertSessionHasErrors('room_type_id');

        $a->post(route('owner.rooms.bulk'), ['property_id' => $this->propertyB->id, 'from' => 1, 'to' => 3, 'price' => 1000])
            ->assertSessionHasErrors('property_id');

        $a->post(route('owner.rooms.import'), [
            'property_id' => $this->propertyB->id,
            'file' => UploadedFile::fake()->createWithContent('k.csv', "nomor,harga\n1,1000\n"),
        ])->assertSessionHasErrors('property_id');

        $a->post(route('owner.room-types.store'), ['name' => 'T', 'base_price' => 1000, 'property_id' => $this->propertyB->id])
            ->assertSessionHasErrors('property_id');

        $this->assertSame(1, $this->withoutTenant(fn () => Room::count()));
    }

    public function test_room_list_filters_cannot_reach_another_tenants_property(): void
    {
        $this->actingAs($this->ownerA)
            ->get(route('owner.rooms.index', ['property_id' => $this->propertyB->id]))
            ->assertOk()
            ->assertDontSee('B-101');
    }
}
