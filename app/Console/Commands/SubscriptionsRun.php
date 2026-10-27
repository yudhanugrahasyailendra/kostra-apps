<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Jobs\RunTenantSubscription;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SubscriptionsRun extends Command
{
    protected $signature = 'subscriptions:run';

    protected $description = 'Antrekan pemeriksaan harian langganan (percobaan, perpanjangan, masa tenggang) untuk setiap tenant yang belum ditutup.';

    public function handle(): int
    {
        $count = 0;

        Tenant::query()
            ->where('status', '!=', TenantStatus::Closed->value)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                RunTenantSubscription::dispatch($id);
                $count++;
            });

        $this->info("{$count} tenant diantrekan.");

        return self::SUCCESS;
    }
}
