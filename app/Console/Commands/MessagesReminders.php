<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Jobs\SendTenantReminders;
use App\Models\Tenant;
use Illuminate\Console\Command;

class MessagesReminders extends Command
{
    protected $signature = 'messages:reminders';

    protected $description = 'Antrekan pengingat tagihan dan kontrak untuk setiap tenant aktif (satu job per tenant).';

    public function handle(): int
    {
        $count = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value])
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                SendTenantReminders::dispatch($id);
                $count++;
            });

        $this->info("{$count} tenant diantrekan.");

        return self::SUCCESS;
    }
}
