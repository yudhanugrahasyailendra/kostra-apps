<?php

namespace App\Console\Commands;

use App\Actions\GenerateMonthlyInvoices;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate {--tenant= : Hanya satu tenant (id)}';

    protected $description = 'Terbitkan tagihan bulanan untuk kontrak aktif (idempoten, aman dijalankan berulang)';

    public function handle(GenerateMonthlyInvoices $generate): int
    {
        $tenants = Tenant::query()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value])
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id');

        $created = 0;
        $failed = 0;

        $tenants->each(function (Tenant $tenant) use ($generate, &$created, &$failed) {
            try {
                $created += $generate->handle($tenant)['created'];
            } catch (\Throwable $e) {
                // Satu tenant gagal tidak boleh menghentikan tenant lain; putaran berikutnya mengulang.
                $failed++;
                report($e);
                $this->error("Tenant {$tenant->id} gagal: ".$e::class);
            }
        });

        $this->info("Tagihan terbit: $created. Tenant gagal: $failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
