<?php

namespace App\Console\Commands;

use App\Actions\SendInvoiceReminders;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SendRemindersCommand extends Command
{
    protected $signature = 'reminders:send {--tenant= : Hanya satu tenant (id)}';

    protected $description = 'Antrekan pengingat tagihan WhatsApp sesuai jadwal tiap tenant (idempoten)';

    public function handle(SendInvoiceReminders $reminders): int
    {
        $queued = 0;
        $failed = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value])
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($reminders, &$queued, &$failed) {
                // Pengingat WhatsApp adalah fitur paket (LGN-02).
                if (! $tenant->plan->hasFeature('whatsapp.reminders')) {
                    return;
                }

                try {
                    $queued += $reminders->handle($tenant);
                } catch (\Throwable $e) {
                    // Satu tenant gagal tidak menghentikan yang lain; putaran berikutnya mengulang.
                    $failed++;
                    report($e);
                    $this->error("Tenant {$tenant->id} gagal: ".$e::class);
                }
            });

        $this->info("Pengingat diantrekan: $queued. Tenant gagal: $failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
