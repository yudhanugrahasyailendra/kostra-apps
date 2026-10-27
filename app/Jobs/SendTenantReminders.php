<?php

namespace App\Jobs;

use App\Actions\Messaging\QueueDueReminders;
use App\Actions\Messaging\QueueLeaseExpiringReminders;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pengingat tagihan dan kontrak untuk SATU tenant. Konteks tenant ditetapkan eksplisit dari id di payload.
 * Aman dijalankan ulang: pesan yang sama tidak dibuat dua kali pada hari yang sama.
 */
class SendTenantReminders implements ShouldBeUnique, ShouldQueue
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

    public function handle(TenantContext $context, QueueDueReminders $dueReminders, QueueLeaseExpiringReminders $leaseReminders): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null || ! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
            return;
        }

        $context->run($tenant, function () use ($dueReminders, $leaseReminders) {
            $dueReminders();
            $leaseReminders();
        });
    }
}
