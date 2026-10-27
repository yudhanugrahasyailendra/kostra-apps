<?php

namespace Tests\Feature\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class LeaseScopesTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant] = $this->tenantWithOwner();
        // 27 Okt 2026 12:00 UTC = 27 Okt 20:00 WITA
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0, 0)->utc());
    }

    /**
     * Kontrak langsung lewat factory; aktif bila $status aktif (active_room_id terisi).
     */
    private function lease(?string $endDate, LeaseStatus $status = LeaseStatus::Active): Lease
    {
        $property = $this->propertyFor($this->tenant);
        $room = Room::factory()->for($this->tenant)->for($property)->create();
        $resident = Resident::factory()->for($this->tenant)->create();

        return Lease::factory()->for($this->tenant)->for($property)->for($room)->for($resident, 'primaryResident')->create([
            'status' => $status,
            'active_room_id' => $status === LeaseStatus::Active ? $room->id : null,
            'start_date' => '2026-01-01',
            'end_date' => $endDate,
        ]);
    }

    #[DataProvider('windows')]
    public function test_expiring_within_is_inclusive_at_the_boundary(int $days): void
    {
        $today = $this->tenant->today();

        $onTheDay = $this->lease($today->addDays($days)->toDateString());
        $dayAfter = $this->lease($today->addDays($days + 1)->toDateString());
        $today0 = $this->lease($today->toDateString());
        $yesterday = $this->lease($today->subDay()->toDateString());
        $ended = $this->lease($today->addDays(2)->toDateString(), LeaseStatus::Ended);
        $open = $this->lease(null);

        $ids = $this->asTenant($this->tenant, fn () => Lease::expiringWithin($days)->pluck('id')->all());

        $this->assertContains($onTheDay->id, $ids);
        $this->assertContains($today0->id, $ids);
        $this->assertNotContains($dayAfter->id, $ids);
        $this->assertNotContains($yesterday->id, $ids);
        $this->assertNotContains($ended->id, $ids);
        $this->assertNotContains($open->id, $ids);
    }

    public static function windows(): array
    {
        return ['30 hari' => [30], '14 hari' => [14], '7 hari' => [7]];
    }

    public function test_past_end_is_a_computed_flag_for_active_leases_only(): void
    {
        $today = $this->tenant->today();
        $late = $this->lease($today->subDay()->toDateString());
        $endsToday = $this->lease($today->toDateString());
        $open = $this->lease(null);
        $endedLate = $this->lease($today->subDays(10)->toDateString(), LeaseStatus::Ended);

        $this->asTenant($this->tenant, function () use ($late, $endsToday, $open, $endedLate) {
            $this->assertTrue(Lease::findOrFail($late->id)->isPastEnd());
            $this->assertFalse(Lease::findOrFail($endsToday->id)->isPastEnd());
            $this->assertFalse(Lease::findOrFail($open->id)->isPastEnd());
            $this->assertFalse(Lease::findOrFail($endedLate->id)->isPastEnd());
        });
    }

    public function test_days_remaining(): void
    {
        $today = $this->tenant->today();
        $a = $this->lease($today->addDays(5)->toDateString());
        $b = $this->lease($today->subDays(3)->toDateString());
        $c = $this->lease(null);

        $this->asTenant($this->tenant, function () use ($a, $b, $c) {
            $this->assertSame(5, Lease::findOrFail($a->id)->daysRemaining());
            $this->assertSame(-3, Lease::findOrFail($b->id)->daysRemaining());
            $this->assertNull(Lease::findOrFail($c->id)->daysRemaining());
        });
    }

    public function test_business_dates_follow_the_tenant_timezone_not_utc(): void
    {
        // 27 Okt 17:00 UTC = 28 Okt 01:00 WITA: hari ini menurut tenant sudah tanggal 28.
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(17, 0, 0)->utc());

        $this->assertSame('2026-10-28', $this->tenant->today()->toDateString());

        $endedYesterdayLocal = $this->lease('2026-10-27');
        $endsTodayLocal = $this->lease('2026-10-28');

        $this->asTenant($this->tenant, function () use ($endedYesterdayLocal, $endsTodayLocal) {
            $this->assertTrue(Lease::findOrFail($endedYesterdayLocal->id)->isPastEnd());
            $this->assertFalse(Lease::findOrFail($endsTodayLocal->id)->isPastEnd());
            $this->assertContains($endsTodayLocal->id, Lease::expiringWithin(7)->pluck('id')->all());
            $this->assertNotContains($endedYesterdayLocal->id, Lease::expiringWithin(7)->pluck('id')->all());
        });
    }
}
