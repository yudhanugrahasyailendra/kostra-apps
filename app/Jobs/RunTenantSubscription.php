<?php

namespace App\Jobs;

use App\Actions\Subscriptions\RunSubscriptionLifecycle;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Masa percobaan, tagihan perpanjangan, masa tenggang, dan penangguhan untuk SATU tenant. Konteks tenant
 * ditetapkan eksplisit dari id di payload. Aman dijalankan ulang.
 */
class RunTenantSubscription implements ShouldBeUnique, ShouldQueue
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

    public function handle(RunSubscriptionLifecycle $lifecycle): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        // Tenant ditutup tidak diproses. Yang ditangguhkan tetap diproses: pembayaran bisa membukanya kembali.
        if ($tenant === null || $tenant->status === TenantStatus::Closed) {
            return;
        }

        $lifecycle($tenant);
    }
}
