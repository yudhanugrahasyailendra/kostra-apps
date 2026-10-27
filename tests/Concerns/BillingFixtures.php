<?php

namespace Tests\Concerns;

use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Actions\Billing\GenerationResult;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fixture penagihan. Tanggal dibekukan supaya hitungan bulan dan pro rata deterministik.
 */
trait BillingFixtures
{
    use CreatesTenantFixtures;

    protected Tenant $tenant;

    protected User $owner;

    protected Property $property;

    private int $roomSeq = 100;

    protected function setUpBilling(string $today = '2026-10-15', string $plan = 'business'): void
    {
        $this->travelTo(CarbonImmutable::parse($today.' 03:00:00', 'UTC'));

        [$this->tenant, $this->owner] = $this->tenantWithOwner($plan);
        $this->property = $this->propertyFor($this->tenant);
    }

    /**
     * Kontrak aktif lewat Action check-in (jalur yang sama dengan UI).
     *
     * @param  array<string, mixed>  $extra
     */
    protected function leaseFor(array $extra = [], ?Tenant $tenant = null, ?Property $property = null): Lease
    {
        $tenant ??= $this->tenant;
        $property ??= $tenant->is($this->tenant) ? $this->property : $this->propertyFor($tenant);

        $room = Room::factory()->for($tenant)->for($property)->create(['number' => (string) ($this->roomSeq++)]);
        $resident = Resident::factory()->for($tenant)->create();

        return $this->checkIn($tenant, $room, $resident, array_merge([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'rent_amount' => 1_500_000,
            'deposit_amount' => 0,
            'deposit_received_on' => null,
        ], $extra));
    }

    protected function generate(?string $period = null, ?Tenant $tenant = null): GenerationResult
    {
        return $this->asTenant($tenant ?? $this->tenant, fn () => app(GenerateMonthlyInvoices::class)($period));
    }

    /**
     * @return Collection<int, Invoice>
     */
    protected function invoices(?Tenant $tenant = null): Collection
    {
        return $this->asTenant($tenant ?? $this->tenant, fn () => Invoice::with('items')->orderBy('id')->get());
    }

    protected function billingSettings(array $attributes, ?Tenant $tenant = null): void
    {
        $this->asTenant($tenant ?? $this->tenant, fn () => BillingSetting::current()->update($attributes));
    }
}
