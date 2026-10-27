<?php

namespace Tests\Feature\Properties;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    public function test_every_page_renders_for_owner_and_staff(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $staff = User::factory()->for($tenant)->staff()->create();
        $property = $this->propertyFor($tenant, ['name' => 'Kos Melati']);
        $type = RoomType::factory()->for($tenant)->for($property)->create(['name' => 'Deluxe AC']);
        $room = Room::factory()->for($tenant)->for($property)->create(['room_type_id' => $type->id, 'number' => '101']);
        $occupied = Room::factory()->occupied()->for($tenant)->for($property)->create(['number' => '102']);

        $ownerPages = [
            route('owner.properties.index'), route('owner.properties.edit', $property),
            route('owner.room-types.index'), route('owner.room-types.edit', $type),
            route('owner.rooms.index'), route('owner.rooms.edit', $room), route('owner.rooms.edit', $occupied),
            route('owner.dashboard'),
        ];

        foreach ($ownerPages as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }

        $this->actingAs($owner)->get(route('owner.rooms.edit', $occupied))
            ->assertSee('ditentukan oleh kontrak')
            ->assertDontSee('Hapus kamar');

        $this->actingAs($owner)->get(route('owner.rooms.index'))
            ->assertSee('Tambah massal')->assertSee('Impor CSV')->assertSee('Rp 1.500.000');

        foreach ([route('owner.properties.index'), route('owner.room-types.index'), route('owner.rooms.index')] as $url) {
            $this->actingAs($staff)->get($url)->assertOk()->assertDontSee('Tambah massal')->assertDontSee('Hapus');
        }

        $this->actingAs($staff)->get(route('owner.rooms.edit', $room))->assertForbidden();
        $this->actingAs($staff)->get(route('owner.properties.edit', $property))->assertForbidden();
    }

    public function test_navigation_links_to_rooms(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertSee('Properti &amp; Kamar', false);
    }
}
