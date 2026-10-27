<?php

namespace Tests\Feature\Properties;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class RoomListAndAuditTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private function queryCountFor(int $rooms): int
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);
        $type = RoomType::factory()->for($tenant)->create();
        Room::factory()->count($rooms)->for($tenant)->for($property)->create(['room_type_id' => $type->id]);

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('owner.rooms.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    public function test_room_list_query_count_does_not_grow_with_the_number_of_rooms(): void
    {
        $small = $this->queryCountFor(5);

        // Tenant kedua pada tes yang sama: jumlah query harus tetap sama untuk 100 kamar.
        $large = $this->queryCountFor(100);

        $this->assertSame($small, $large, 'Daftar kamar terkena N+1.');
    }

    public function test_list_of_100_rooms_renders_and_filters_work(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);
        Room::factory()->count(95)->for($tenant)->for($property)->create();
        Room::factory()->count(3)->maintenance()->for($tenant)->for($property)->create();
        Room::factory()->count(2)->occupied()->for($tenant)->for($property)->create(['floor' => 2]);

        $this->actingAs($owner)->get(route('owner.rooms.index'))->assertOk()->assertSee('Perawatan');

        $this->actingAs($owner)->get(route('owner.rooms.index', ['status' => 'maintenance']))
            ->assertOk()
            ->assertViewHas('rooms', fn ($rooms) => $rooms->total() === 3);

        $this->actingAs($owner)->get(route('owner.rooms.index', ['floor' => 2]))
            ->assertViewHas('rooms', fn ($rooms) => $rooms->total() === 2);

        $this->actingAs($owner)->get(route('owner.rooms.index', ['status' => 'bukan-status']))
            ->assertSessionHasErrors('status');
    }

    public function test_price_change_is_audited_with_old_and_new_values(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->create(['price' => 1_500_000]);

        $this->actingAs($owner)->put(route('owner.rooms.update', $room), [
            'number' => $room->number, 'price' => '1.750.000',
        ])->assertSessionHasNoErrors();

        $log = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'room')
            ->where('auditable_id', $room->id)
            ->where('event', AuditEvent::Updated->value)
            ->latest('id')->firstOrFail());

        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertEquals(1_500_000, $log->old_values['price']);
        $this->assertEquals(1_750_000, $log->new_values['price']);
    }

    public function test_deleting_a_room_is_audited(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->create();

        $this->actingAs($owner)->delete(route('owner.rooms.destroy', $room))->assertRedirect();

        $this->assertTrue($this->withoutTenant(fn () => AuditLog::where('auditable_type', 'room')
            ->where('auditable_id', $room->id)->where('event', 'deleted')->exists()));
    }

    public function test_suspended_tenant_can_read_rooms_but_not_change_them(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->suspended()->create();
        $owner = User::factory()->for($tenant)->create();
        $property = $this->propertyFor($tenant);
        $room = Room::factory()->for($tenant)->for($property)->create();

        $this->actingAs($owner)->get(route('owner.rooms.index'))->assertOk();
        $this->actingAs($owner)->delete(route('owner.rooms.destroy', $room))->assertForbidden();
        $this->actingAs($owner)->post(route('owner.rooms.bulk'), ['property_id' => $property->id, 'from' => 1, 'to' => 2, 'price' => 1000])->assertForbidden();
        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_dashboard_shows_the_floor_plan_with_status_text(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant, ['name' => 'Kos Melati']);
        Room::factory()->for($tenant)->for($property)->create(['number' => '101', 'floor' => 1]);
        Room::factory()->occupied()->for($tenant)->for($property)->create(['number' => '102', 'floor' => 1]);
        Room::factory()->maintenance()->for($tenant)->for($property)->create(['number' => '201', 'floor' => 2]);

        $this->actingAs($owner)->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('Denah Kos Melati')
            ->assertSee('Lantai 1')
            ->assertSee('Lantai 2')
            ->assertSeeInOrder(['101', 'Kosong'])
            ->assertSeeInOrder(['102', 'Terisi'])
            ->assertSeeInOrder(['201', 'Perawatan']);
    }
}
