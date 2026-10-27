<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Jobs\RunTenantBilling;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BillingRun extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Antrekan penerbitan tagihan dan penandaan terlambat untuk setiap tenant aktif (satu job per tenant).';

    public function handle(): int
    {
        $count = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value])
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                RunTenantBilling::dispatch($id);
                $count++;
            });

        $this->info("{$count} tenant diantrekan.");

        return self::SUCCESS;
    }
}
