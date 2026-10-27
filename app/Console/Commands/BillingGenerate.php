<?php

namespace App\Console\Commands;

use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class BillingGenerate extends Command
{
    protected $signature = 'billing:generate {tenant : id tenant} {--period= : Periode YYYY-MM (mis. untuk mengisi data lama)}';

    protected $description = 'Terbitkan tagihan bulanan untuk satu tenant, opsional untuk periode tertentu. Idempoten.';

    public function handle(TenantContext $context, GenerateMonthlyInvoices $generate): int
    {
        $tenant = Tenant::query()->find($this->argument('tenant'));

        if ($tenant === null) {
            $this->error('Tenant tidak ditemukan.');

            return self::FAILURE;
        }

        $period = $this->option('period') ?: null;

        if ($period !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            $this->error('Periode harus berformat YYYY-MM.');

            return self::FAILURE;
        }

        $result = $context->run($tenant, fn () => $generate($period));

        $this->info("Terbit: {$result->issued}, dilewati: {$result->skipped}, gagal: {$result->failed}.");

        return $result->failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
