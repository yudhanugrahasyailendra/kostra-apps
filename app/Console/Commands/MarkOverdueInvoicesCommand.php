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
        $failed = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value])
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($mark, &$changed, &$failed) {
                try {
                    $changed += $mark->handle($tenant);
                } catch (\Throwable $e) {
                    // Satu tenant gagal tidak boleh menghentikan tenant lain; putaran berikutnya mengulang.
                    $failed++;
                    report($e);
                    $this->error("Tenant {$tenant->id} gagal: ".$e::class);
                }
            });

        $this->info("Tagihan lewat jatuh tempo: $changed. Tenant gagal: $failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
