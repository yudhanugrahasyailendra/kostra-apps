<?php

namespace Tests\Feature\Leases;

use App\Contracts\OutstandingBalance;
use App\Enums\DepositStatus;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class CheckOutTest extends TestCase
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
        $this->room = Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '101']);
        $this->resident = Resident::factory()->for($this->tenant)->create();
    }

    private function checkOutRequest(Lease $lease, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.leases.check-out', $lease), array_merge([
            'checkout_date' => $this->tenant->today()->toDateString(),
            'ended_reason' => 'moved_out',
        ], $data));
    }

    private function reload(Lease $lease): Lease
    {
        return $this->asTenant($this->tenant, fn () => Lease::with('deposit')->findOrFail($lease->id));
    }

    public function test_check_out_closes_the_lease_and_frees_the_room_and_resident(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->checkOutRequest($lease, ['checkout_notes' => 'Kamar bersih'])->assertSessionHasNoErrors()->assertRedirect(route('owner.leases.show', $lease));

        $fresh = $this->reload($lease);
        $this->assertSame(LeaseStatus::Ended, $fresh->status);
        $this->assertNull($fresh->active_room_id);
        $this->assertSame(LeaseEndReason::MovedOut, $fresh->ended_reason);
        $this->assertSame('Kamar bersih', $fresh->checkout_notes);
        $this->assertSame($this->tenant->today()->toDateString(), $fresh->checked_out_at->timezone('Asia/Makassar')->toDateString());

        $this->asTenant($this->tenant, function () {
            $this->assertSame(RoomStatus::Vacant, Room::findOrFail($this->room->id)->status);
            $this->assertFalse(Resident::findOrFail($this->resident->id)->is_active);
        });
    }

    public function test_deposit_is_fully_refunded_without_deduction(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);
        $this->checkOutRequest($lease)->assertSessionHasNoErrors();

        $deposit = $this->reload($lease)->deposit;
        $this->assertSame(DepositStatus::Refunded, $deposit->status);
        $this->assertSame(0, $deposit->deduction_amount);
        $this->assertSame(500_000, $deposit->refund_amount);
        $this->assertSame($this->tenant->today()->toDateString(), $deposit->refunded_at->toDateString());
    }

    public function test_refund_is_deposit_minus_deduction(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);
        $this->checkOutRequest($lease, ['deduction_amount' => 'Rp 150.000', 'deduction_note' => 'Kunci hilang'])->assertSessionHasNoErrors();

        $deposit = $this->reload($lease)->deposit;
        $this->assertSame(DepositStatus::PartiallyRefunded, $deposit->status);
        $this->assertSame(150_000, $deposit->deduction_amount);
        $this->assertSame(350_000, $deposit->refund_amount);
        $this->assertSame('Kunci hilang', $deposit->deduction_note);
    }

    public function test_deducting_the_whole_deposit_forfeits_it(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);
        $this->checkOutRequest($lease, ['deduction_amount' => 500000, 'deduction_note' => 'Kerusakan berat'])->assertSessionHasNoErrors();

        $deposit = $this->reload($lease)->deposit;
        $this->assertSame(DepositStatus::Forfeited, $deposit->status);
        $this->assertSame(0, $deposit->refund_amount);
        $this->assertNull($deposit->refunded_at);
    }

    public function test_deduction_rules(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->checkOutRequest($lease, ['deduction_amount' => 600000, 'deduction_note' => 'Terlalu besar'])->assertSessionHasErrors('deduction_amount');
        $this->checkOutRequest($lease, ['deduction_amount' => 100000])->assertSessionHasErrors('deduction_note');

        $this->assertSame(LeaseStatus::Active, $this->reload($lease)->status);
    }

    public function test_pending_deposit_cannot_be_deducted_and_stays_pending(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident, ['deposit_received_on' => null]);

        $this->checkOutRequest($lease, ['deduction_amount' => 100000, 'deduction_note' => 'x'])->assertSessionHasErrors('deduction_amount');
        $this->checkOutRequest($lease)->assertSessionHasNoErrors();

        $deposit = $this->reload($lease)->deposit;
        $this->assertSame(DepositStatus::Pending, $deposit->status);
        $this->assertSame(0, $deposit->refund_amount);
    }

    public function test_staff_can_check_out_but_cannot_deduct_the_deposit(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->checkOutRequest($lease, ['deduction_amount' => 100000, 'deduction_note' => 'x'], $staff)->assertForbidden();
        $this->assertSame(LeaseStatus::Active, $this->reload($lease)->status);

        $this->checkOutRequest($lease, [], $staff)->assertSessionHasNoErrors();
        $this->assertSame(LeaseStatus::Ended, $this->reload($lease)->status);
    }

    public function test_date_and_reason_rules(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->checkOutRequest($lease, ['checkout_date' => $this->tenant->today()->addDay()->toDateString()])->assertSessionHasErrors('checkout_date');
        $this->checkOutRequest($lease, ['checkout_date' => $this->tenant->today()->subYear()->toDateString()])->assertSessionHasErrors('checkout_date');
        $this->checkOutRequest($lease, ['ended_reason' => 'expired'])->assertSessionHasErrors('ended_reason');

        $this->assertSame(LeaseStatus::Active, $this->reload($lease)->status);
    }

    public function test_cannot_check_out_twice(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);
        $this->checkOutRequest($lease)->assertSessionHasNoErrors();

        $this->checkOutRequest($lease)->assertSessionHasErrors('general');
        $this->assertSame(500_000, $this->reload($lease)->deposit->refund_amount);
    }

    public function test_resident_with_another_active_lease_stays_active(): void
    {
        $second = Room::factory()->for($this->tenant)->for($this->property)->create(['number' => '102']);
        $first = $this->checkIn($this->tenant, $this->room, $this->resident);
        $this->checkIn($this->tenant, $second, $this->resident);

        $this->checkOutRequest($first)->assertSessionHasNoErrors();

        $this->assertTrue($this->asTenant($this->tenant, fn () => Resident::findOrFail($this->resident->id)->is_active));
    }

    public function test_room_can_be_rented_again_and_history_is_kept(): void
    {
        $first = $this->checkIn($this->tenant, $this->room, $this->resident);
        $this->checkOutRequest($first)->assertSessionHasNoErrors();

        $newcomer = Resident::factory()->for($this->tenant)->create();
        $second = $this->checkIn($this->tenant, $this->room->refresh(), $newcomer);

        $this->asTenant($this->tenant, function () use ($first, $second) {
            $this->assertSame(2, Lease::where('room_id', $this->room->id)->count());
            $this->assertSame(LeaseStatus::Ended, Lease::findOrFail($first->id)->status);
            $this->assertSame(LeaseStatus::Active, Lease::findOrFail($second->id)->status);
            $this->assertSame(RoomStatus::Occupied, Room::findOrFail($this->room->id)->status);
        });
    }

    public function test_resident_with_active_lease_cannot_be_anonymized_until_checked_out(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->actingAs($this->owner)->delete(route('owner.residents.destroy', $this->resident))->assertSessionHasErrors('general');
        $this->assertFalse($this->asTenant($this->tenant, fn () => Resident::findOrFail($this->resident->id)->trashed()));

        $this->checkOutRequest($lease)->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->delete(route('owner.residents.destroy', $this->resident))->assertRedirect(route('owner.residents.index'));
        $this->assertTrue($this->asTenant($this->tenant, fn () => Resident::withTrashed()->findOrFail($this->resident->id)->trashed()));
    }

    public function test_outstanding_balance_comes_from_the_bound_implementation(): void
    {
        $lease = $this->checkIn($this->tenant, $this->room, $this->resident);

        $this->actingAs($this->owner)->get(route('owner.leases.show', $lease))->assertOk()->assertDontSee('Sisa tagihan belum dibayar');

        $this->app->bind(OutstandingBalance::class, fn () => new class implements OutstandingBalance
        {
            public function forLease(Lease $lease): int
            {
                return 250_000;
            }
        });

        $this->actingAs($this->owner)->get(route('owner.leases.show', $lease))
            ->assertOk()->assertSee('Sisa tagihan belum dibayar')->assertSee('Rp 250.000');
    }
}
