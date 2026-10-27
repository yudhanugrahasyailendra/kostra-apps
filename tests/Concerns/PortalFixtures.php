<?php

namespace Tests\Concerns;

use App\Models\Invoice;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fixture portal penghuni di atas fixture keuangan. Token asli hanya ada di tes (di database hanya hash-nya).
 */
trait PortalFixtures
{
    use FinanceFixtures;

    protected function setUpPortal(string $today = '2026-10-15', string $plan = 'business'): void
    {
        $this->setUpFinance($today, $plan);
        RateLimiter::clear('portal-token-failures:127.0.0.1');
    }

    /**
     * Membuat tautan portal untuk penghuni dan mengembalikan token ASLI.
     */
    protected function tokenFor(Resident|int $resident, ?Tenant $tenant = null, string $state = ''): string
    {
        $tenant ??= $this->tenant;
        $residentId = $resident instanceof Resident ? $resident->getKey() : $resident;
        $raw = PortalToken::generate();

        $this->asTenant($tenant, function () use ($tenant, $residentId, $raw, $state) {
            $factory = PortalToken::factory()->for($tenant)->forToken($raw);

            if ($state !== '') {
                $factory = $factory->{$state}();
            }

            $factory->create(['resident_id' => $residentId]);
        });

        return $raw;
    }

    protected function portalUrl(string $token, string $path = ''): string
    {
        return '/p/'.$token.$path;
    }

    /**
     * Satu penghuni dengan kontrak aktif, tagihan terbuka, dan tautan portal.
     *
     * @return array{0: string, 1: Invoice, 2: Resident}
     */
    protected function residentWithInvoice(array $leaseExtra = [], ?Tenant $tenant = null): array
    {
        $tenant ??= $this->tenant;

        if ($tenant->is($this->tenant)) {
            $invoice = $this->openInvoice($leaseExtra);
        } else {
            $lease = $this->leaseFor($leaseExtra, $tenant);
            $this->generate(null, $tenant);
            $invoice = $this->asTenant($tenant, fn () => Invoice::query()->where('lease_id', $lease->id)->latest('id')->firstOrFail());
        }

        $resident = $this->asTenant($tenant, fn () => Resident::findOrFail($invoice->resident_id));

        return [$this->tokenFor($resident, $tenant), $invoice, $resident];
    }
}
