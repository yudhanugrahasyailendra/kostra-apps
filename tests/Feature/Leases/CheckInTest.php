<?php

namespace Tests\Feature\Leases;

use App\Enums\DepositStatus;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\AuditLog;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Room $room;

    private Property $property;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->tenantWithOwner('pro');
        $this->property = $this->propertyFor($this->tenant);
        $this->room = Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '101', 'price' => 1_750_000]);
        $this->resident = Resident::factory()->inactive()->for($this->tenant)->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'room_id' => $this->room->id,
            'primary_resident_id' => $this->resident->id,
            'start_date' => $this->tenant->today()->subDays(3)->toDateString(),
            'end_date' => $this->tenant->today()->addMonths(6)->toDateString(),
            'deposit_amount' => '500.000',
            'deposit_received_on' => $this->tenant->today()->subDays(3)->toDateString(),
        ], $overrides);
    }

    public function test_check_in_creates_lease_residents_deposit_and_occupies_the_room(): void
    {
        $response = $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['due_day' => 5, 'checkin_notes' => 'AC dingin']));

        $lease = $this->asTenant($this->tenant, fn () => Lease::with('deposit')->firstOrFail());
        $response->assertSessionHasNoErrors()->assertRedirect(route('owner.leases.show', $lease));

        $this->assertSame(LeaseStatus::Active, $lease->status);
        $this->assertSame($this->room->id, $lease->room_id);
        $this->assertSame($this->room->id, $lease->active_room_id);
        $this->assertSame($this->room->property_id, $lease->property_id);
        $this->assertSame($this->resident->id, $lease->primary_resident_id);
        $this->assertSame(1_750_000, $lease->rent_amount);
        $this->assertSame(5, $lease->due_day);
        $this->assertSame('AC dingin', $lease->checkin_notes);
        $this->assertSame($this->tenant->id, $lease->tenant_id);

        $this->assertSame(500_000, $lease->deposit->amount);
        $this->assertSame(DepositStatus::Held, $lease->deposit->status);

        $this->asTenant($this->tenant, function () {
            $this->assertSame(RoomStatus::Occupied, Room::findOrFail($this->room->id)->status);
            $this->assertTrue(Resident::findOrFail($this->resident->id)->is_active);
        });

        $this->assertTrue($this->withoutTenant(fn () => AuditLog::where('auditable_type', 'lease')->where('event', 'created')->exists()));
    }

    public function test_deposit_without_received_date_stays_pending(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident, ['deposit_received_on' => null]);

        $this->assertSame(DepositStatus::Pending, $this->asTenant($this->tenant, fn () => $lease->deposit()->firstOrFail()->status));
    }

    public function test_lease_rent_is_a_snapshot_independent_of_later_room_price_changes(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->asTenant($this->tenant, fn () => Room::findOrFail($this->room->id)->update(['price' => 2_500_000]));

        $this->assertSame(1_750_000, $this->asTenant($this->tenant, fn () => Lease::findOrFail($lease->id)->rent_amount));
    }

    public function test_agreed_rent_overrides_room_price(): void
    {
        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['rent_amount' => 'Rp 1.600.000']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1_600_000, $this->asTenant($this->tenant, fn () => Lease::firstOrFail()->rent_amount));

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['rent_amount' => '1500.50']))
            ->assertSessionHasErrors('rent_amount');
    }

    public function test_second_check_in_on_the_same_room_is_refused_with_a_clear_message(): void
    {
        $this->checkIn($this->tenant, $this->room, $this->resident);
        $other = Resident::factory()->for($this->tenant)->create();

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['primary_resident_id' => $other->id]))
            ->assertSessionHasErrors('room_id');

        $this->assertStringContainsString('sudah terisi', session('errors')->first('room_id'));
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Lease::count()));
    }

    public function test_stale_room_status_cannot_double_book_the_room(): void
    {
        $this->checkIn($this->tenant, $this->room, $this->resident);

        // Status kamar tertinggal/rusak (kosong) padahal kontrak aktif ada: tetap harus ditolak.
        $this->withoutTenant(fn () => Room::whereKey($this->room->id)->update(['status' => 'vacant']));
        $other = Resident::factory()->for($this->tenant)->create();

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['primary_resident_id' => $other->id]))
            ->assertSessionHasErrors('room_id');

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Lease::count()));
    }

    public function test_database_itself_rejects_two_active_leases_on_one_room(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->asTenant($this->tenant, fn () => Lease::create([
            'property_id' => $lease->property_id,
            'room_id' => $lease->room_id,
            'primary_resident_id' => $this->resident->id,
            'status' => LeaseStatus::Active,
            'active_room_id' => $lease->room_id,
            'start_date' => now()->toDateString(),
            'billing_cycle' => 'monthly',
            'rent_amount' => 1_000_000,
        ]));
    }

    public function test_ended_leases_do_not_block_a_room_because_active_room_id_is_null(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->asTenant($this->tenant, function () use ($lease) {
            Lease::findOrFail($lease->id)->update(['status' => LeaseStatus::Ended, 'active_room_id' => null]);
            Room::findOrFail($lease->room_id)->update(['status' => RoomStatus::Vacant]);
        });

        $this->checkIn($this->tenant, $this->room->refresh(), $this->resident);

        $this->assertSame(2, $this->asTenant($this->tenant, fn () => Lease::where('room_id', $this->room->id)->count()));
    }

    public function test_maintenance_room_cannot_be_checked_in(): void
    {
        $maintenance = Room::factory()->maintenance()->for($this->tenant)->for($this->property)->create();

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['room_id' => $maintenance->id]))
            ->assertSessionHasErrors('room_id');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Lease::count()));
    }

    public function test_everything_is_rolled_back_when_the_deposit_cannot_be_created(): void
    {
        LeaseDeposit::creating(fn () => throw new RuntimeException('deposit gagal'));

        try {
            $this->checkIn($this->tenant, $this->room, $this->resident);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->asTenant($this->tenant, function () {
            $this->assertSame(0, Lease::count());
            $this->assertSame(0, LeaseResident::count());
            $this->assertSame(RoomStatus::Vacant, Room::findOrFail($this->room->id)->status);
            $this->assertFalse(Resident::findOrFail($this->resident->id)->is_active);
        });
    }

    public function test_two_residents_share_a_lease_with_exactly_one_primary(): void
    {
        $partner = Resident::factory()->for($this->tenant)->create();

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['other_resident_ids' => [$partner->id]]))
            ->assertSessionHasNoErrors();

        $this->asTenant($this->tenant, function () use ($partner) {
            $members = LeaseResident::orderBy('id')->get();
            $this->assertCount(2, $members);
            $this->assertSame(1, $members->where('is_primary', true)->count());
            $this->assertSame($this->resident->id, $members->firstWhere('is_primary', true)->resident_id);
            $this->assertSame($this->resident->id, Lease::firstOrFail()->primary_resident_id);
            $this->assertTrue(Resident::findOrFail($partner->id)->is_active);
        });
    }

    public function test_primary_cannot_be_repeated_and_more_than_six_residents_are_refused(): void
    {
        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['other_resident_ids' => [$this->resident->id]]))
            ->assertSessionHasErrors('other_resident_ids.0');

        $many = Resident::factory()->count(6)->for($this->tenant)->create()->pluck('id')->all();

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['other_resident_ids' => $many]))
            ->assertSessionHasErrors('other_resident_ids');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Lease::count()));
    }

    public function test_date_rules(): void
    {
        $post = fn (array $o) => $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload($o));

        $post(['start_date' => $this->tenant->today()->addDay()->toDateString()])->assertSessionHasErrors('start_date');
        $post(['start_date' => $this->tenant->today()->subYears(6)->toDateString(), 'end_date' => null])->assertSessionHasErrors('start_date');
        $post(['end_date' => $this->tenant->today()->subDays(10)->toDateString()])->assertSessionHasErrors('end_date');
        $post(['due_day' => 31])->assertSessionHasErrors('due_day');
        $post(['deposit_received_on' => $this->tenant->today()->addDay()->toDateString()])->assertSessionHasErrors('deposit_received_on');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Lease::count()));

        // Tanpa tanggal akhir diperbolehkan.
        $post(['end_date' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->asTenant($this->tenant, fn () => Lease::firstOrFail()->end_date));
    }

    public function test_check_in_timestamp_follows_the_tenant_timezone(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(17, 0, 0)->utc()); // 28 Okt 01:00 WITA

        $lease = $this->checkIn($this->tenant, $this->room, $this->resident, [
            'start_date' => '2026-10-28', 'end_date' => '2027-04-28', 'deposit_received_on' => null,
        ]);

        // Awal 28 Okt WITA = 27 Okt 16:00 UTC, dan kembali menjadi 28 Okt di zona waktu tenant.
        $this->assertSame('2026-10-27 16:00:00', $lease->checked_in_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-28', $lease->checked_in_at->timezone('Asia/Makassar')->toDateString());
    }

    public function test_staff_can_check_in_and_suspended_tenants_cannot(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)->post(route('owner.leases.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Lease::count()));

        $this->seedPlans();
        $suspended = Tenant::factory()->suspended()->create();
        $ownerS = User::factory()->for($suspended)->create();
        $roomS = Room::factory()->for($suspended)->for($this->propertyFor($suspended))->create();
        $residentS = Resident::factory()->for($suspended)->create();

        $this->actingAs($ownerS)->post(route('owner.leases.store'), [
            'room_id' => $roomS->id, 'primary_resident_id' => $residentS->id, 'start_date' => $suspended->today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_cannot_use_another_tenants_room_or_resident(): void
    {
        $other = Tenant::factory()->create();
        $otherRoom = Room::factory()->for($other)->for($this->propertyFor($other))->create();
        $otherResident = Resident::factory()->for($other)->create();

        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['room_id' => $otherRoom->id]))
            ->assertSessionHasErrors('room_id');
        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['primary_resident_id' => $otherResident->id]))
            ->assertSessionHasErrors('primary_resident_id');
        $this->actingAs($this->owner)->post(route('owner.leases.store'), $this->payload(['other_resident_ids' => [$otherResident->id]]))
            ->assertSessionHasErrors('other_resident_ids.0');

        $this->assertSame(0, $this->withoutTenant(fn () => Lease::count()));
    }
}
