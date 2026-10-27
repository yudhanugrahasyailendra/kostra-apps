<?php

namespace App\Jobs;

use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Actions\Billing\MarkOverdueInvoices;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Penerbitan tagihan dan penandaan terlambat untuk SATU tenant. Konteks tenant ditetapkan eksplisit
 * dari id di payload (job tidak punya pengguna login). Aman dijalankan ulang.
 */
class RunTenantBilling implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $tenantId) {}

    public function uniqueId(): string
    {
        return (string) $this->tenantId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(TenantContext $context, GenerateMonthlyInvoices $generate, MarkOverdueInvoices $markOverdue): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        // Tenant yang ditangguhkan atau ditutup hanya baca: tidak ada tagihan baru.
        if ($tenant === null || ! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
            return;
        }

        $context->run($tenant, function () use ($generate, $markOverdue) {
            $generate();
            $markOverdue();
        });
    }
}
