<?php

namespace Tests\Feature\Leases;

use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class RenewAndTransferTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    private Room $room;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->owner] = $this->tenantWithOwner('pro');
        $this->property = $this->propertyFor($this->tenant);
        $this->room = Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '101', 'price' => 1_500_000]);
        $this->resident = Resident::factory()->for($this->tenant)->create();
    }

    private function active(array $extra = []): Lease
    {
        return $this->checkIn($this->tenant, $this->room, $this->resident, array_merge([
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'due_day' => 7,
        ], $extra));
    }

    private function room(string $number, array $attributes = []): Room
    {
        return Room::factory()->for($this->tenant)->for($this->property)->create(array_merge(['number' => $number], $attributes));
    }

    // ---- Perpanjangan ----

    public function test_renewal_continues_the_lease_and_keeps_the_room_occupied(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $old = $this->active();
        $partner = Resident::factory()->for($this->tenant)->create();
        $this->asTenant($this->tenant, fn () => LeaseResident::create(['lease_id' => $old->id, 'resident_id' => $partner->id, 'is_primary' => false]));

        $this->actingAs($this->owner)->post(route('owner.leases.renew', $old), ['end_date' => '2027-06-30'])
            ->assertSessionHasNoErrors();

        $this->asTenant($this->tenant, function () use ($old, $partner) {
            $new = Lease::where('renewed_from_lease_id', $old->id)->firstOrFail();
            $oldFresh = Lease::findOrFail($old->id);

            // Tanggal mulai menyambung dan rantai tersimpan.
            $this->assertSame('2027-01-01', $new->start_date->toDateString());
            $this->assertSame('2027-06-30', $new->end_date->toDateString());
            $this->assertSame($old->id, $new->renewed_from_lease_id);

            // Kontrak lama selesai, yang baru memegang kamar.
            $this->assertSame(LeaseStatus::Ended, $oldFresh->status);
            $this->assertSame(LeaseEndReason::Expired, $oldFresh->ended_reason);
            $this->assertNull($oldFresh->active_room_id);
            $this->assertSame(LeaseStatus::Active, $new->status);
            $this->assertSame($this->room->id, $new->active_room_id);
            $this->assertSame(1, Lease::where('active_room_id', $this->room->id)->count());
            $this->assertSame(RoomStatus::Occupied, Room::findOrFail($this->room->id)->status);

            // Diwarisi: harga, jatuh tempo, penghuni (satu penanggung jawab), dan deposit.
            $this->assertSame($oldFresh->rent_amount, $new->rent_amount);
            $this->assertSame(7, $new->due_day);
            $this->assertSame($old->primary_resident_id, $new->primary_resident_id);
            $members = LeaseResident::where('lease_id', $new->id)->get();
            $this->assertEqualsCanonicalizing([$this->resident->id, $partner->id], $members->pluck('resident_id')->all());
            $this->assertSame(1, $members->where('is_primary', true)->count());
            $this->assertSame($new->id, LeaseDeposit::firstOrFail()->lease_id);
            $this->assertSame(1, LeaseDeposit::count());
        });
    }

    public function test_renewal_rules(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $lease = $this->active();

        $this->actingAs($this->owner)->post(route('owner.leases.renew', $lease), ['end_date' => '2026-12-31'])
            ->assertSessionHasErrors('end_date');
        $this->actingAs($this->owner)->post(route('owner.leases.renew', $lease), [])->assertSessionHasErrors('end_date');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Lease::whereNotNull('renewed_from_lease_id')->count()));

        // Tanpa tanggal akhir tidak bisa diperpanjang.
        $open = $this->checkIn($this->tenant, $this->room($number = '102'), $this->resident, ['end_date' => null]);
        $this->actingAs($this->owner)->post(route('owner.leases.renew', $open), ['end_date' => '2027-12-31'])->assertSessionHasErrors('general');
    }

    public function test_an_ended_lease_cannot_be_renewed_again(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $old = $this->active();
        $this->actingAs($this->owner)->post(route('owner.leases.renew', $old), ['end_date' => '2027-06-30'])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('owner.leases.renew', $old), ['end_date' => '2028-06-30'])->assertSessionHasErrors('general');

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Lease::whereNotNull('renewed_from_lease_id')->count()));
        $this->actingAs($this->owner)->get(route('owner.leases.show', $old))->assertOk()->assertSee('Diperpanjang menjadi');
    }

    // ---- Pindah kamar ----

    public function test_transfer_moves_the_lease_and_keeps_history(): void
    {
        $lease = $this->active();
        $target = $this->room('102', ['price' => 1_900_000]);

        $this->actingAs($this->owner)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $target->id,
            'transferred_on' => $this->tenant->today()->toDateString(),
            'new_rent_amount' => '1.900.000',
            'reason' => 'AC rusak',
        ])->assertSessionHasNoErrors();

        $this->asTenant($this->tenant, function () use ($lease, $target) {
            $fresh = Lease::findOrFail($lease->id);
            $this->assertSame($target->id, $fresh->room_id);
            $this->assertSame($target->id, $fresh->active_room_id);
            $this->assertSame(1_900_000, $fresh->rent_amount);
            $this->assertSame(RoomStatus::Vacant, Room::findOrFail($this->room->id)->status);
            $this->assertSame(RoomStatus::Occupied, Room::findOrFail($target->id)->status);
            $this->assertNull(Lease::where('active_room_id', $this->room->id)->first());

            $transfer = RoomTransfer::firstOrFail();
            $this->assertSame($this->room->id, $transfer->from_room_id);
            $this->assertSame($target->id, $transfer->to_room_id);
            $this->assertSame(1_900_000, $transfer->new_rent_amount);
            $this->assertSame('AC rusak', $transfer->reason);
            $this->assertSame($this->owner->id, $transfer->created_by);
        });

        $this->actingAs($this->owner)->get(route('owner.leases.show', $lease))->assertOk()->assertSee('Riwayat pindah kamar')->assertSee('AC rusak');
    }

    public function test_transfer_without_new_rent_keeps_the_agreed_rent_and_old_room_can_be_reused(): void
    {
        $lease = $this->active();
        $target = $this->room('102', ['price' => 9_000_000]);

        $this->actingAs($this->owner)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $target->id, 'transferred_on' => $this->tenant->today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1_500_000, $this->asTenant($this->tenant, fn () => Lease::findOrFail($lease->id)->rent_amount));

        $newcomer = Resident::factory()->for($this->tenant)->create();
        $this->checkIn($this->tenant, $this->room->refresh(), $newcomer);
        $this->assertSame(2, $this->asTenant($this->tenant, fn () => Lease::active()->count()));
    }

    public function test_transfer_to_the_same_occupied_or_maintenance_room_is_refused(): void
    {
        $lease = $this->active();
        $busy = $this->room('102');
        $this->checkIn($this->tenant, $busy, Resident::factory()->for($this->tenant)->create());
        $repair = $this->room('103', ['status' => 'maintenance']);

        $go = fn (int $roomId) => $this->actingAs($this->owner)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $roomId, 'transferred_on' => $this->tenant->today()->toDateString(),
        ]);

        $go($this->room->id)->assertSessionHasErrors('to_room_id');
        $go($busy->id)->assertSessionHasErrors('to_room_id');
        $go($repair->id)->assertSessionHasErrors('to_room_id');

        $this->asTenant($this->tenant, function () use ($lease) {
            $this->assertSame($this->room->id, Lease::findOrFail($lease->id)->room_id);
            $this->assertSame(0, RoomTransfer::count());
        });
    }

    public function test_transfer_date_rules_and_cross_tenant_room(): void
    {
        $lease = $this->active(['start_date' => $this->tenant->today()->subDays(10)->toDateString()]);
        $target = $this->room('102');
        $other = Tenant::factory()->create();
        $foreign = Room::factory()->for($other)->for($this->propertyFor($other))->create();

        $post = fn (array $d) => $this->actingAs($this->owner)->post(route('owner.leases.transfer', $lease), $d);

        $post(['to_room_id' => $target->id, 'transferred_on' => $this->tenant->today()->addDay()->toDateString()])->assertSessionHasErrors('transferred_on');
        $post(['to_room_id' => $target->id, 'transferred_on' => $this->tenant->today()->subDays(30)->toDateString()])->assertSessionHasErrors('transferred_on');
        $post(['to_room_id' => $foreign->id, 'transferred_on' => $this->tenant->today()->toDateString()])->assertSessionHasErrors('to_room_id');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => RoomTransfer::count()));
    }

    public function test_transfer_to_another_property_updates_the_lease_property(): void
    {
        $lease = $this->active();
        $second = $this->propertyFor($this->tenant);
        $target = Room::factory()->for($this->tenant)->for($second)->create(['number' => '201']);

        $this->actingAs($this->owner)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $target->id, 'transferred_on' => $this->tenant->today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($second->id, $this->asTenant($this->tenant, fn () => Lease::findOrFail($lease->id)->property_id));
    }

    public function test_staff_can_transfer_but_cannot_change_the_rent(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();
        $lease = $this->active();
        $target = $this->room('102');
        $other = $this->room('103');

        $this->actingAs($staff)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $target->id, 'transferred_on' => $this->tenant->today()->toDateString(), 'new_rent_amount' => 2000000,
        ])->assertForbidden();
        $this->assertSame($this->room->id, $this->asTenant($this->tenant, fn () => Lease::findOrFail($lease->id)->room_id));

        $this->actingAs($staff)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $other->id, 'transferred_on' => $this->tenant->today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $this->asTenant($this->tenant, fn () => Lease::findOrFail($lease->id)->room_id));
    }

    public function test_ended_lease_cannot_be_transferred(): void
    {
        $lease = $this->active(['start_date' => $this->tenant->today()->subDays(5)->toDateString()]);
        $target = $this->room('102');
        $this->actingAs($this->owner)->post(route('owner.leases.check-out', $lease), [
            'checkout_date' => $this->tenant->today()->toDateString(), 'ended_reason' => 'moved_out',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('owner.leases.transfer', $lease), [
            'to_room_id' => $target->id, 'transferred_on' => $this->tenant->today()->toDateString(),
        ])->assertSessionHasErrors('general');
    }
}
