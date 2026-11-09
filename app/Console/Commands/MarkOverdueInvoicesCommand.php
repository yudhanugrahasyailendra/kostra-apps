<?php

namespace App\Console\Commands;

use App\Actions\MarkOverdueInvoices;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Ubah tagihan yang lewat jatuh tempo menjadi overdue';

    public function handle(MarkOverdueInvoices $mark): int
    {
        $changed = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value])
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($mark, &$changed) {
                $changed += $mark->handle($tenant);
            });

        $this->info("Tagihan lewat jatuh tempo: $changed.");

        return self::SUCCESS;
    }
}
