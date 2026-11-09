<?php

namespace App\Console\Commands;

use App\Actions\ProcessSubscriptions;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ProcessSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:process {--tenant= : Hanya satu tenant (id)}';

    protected $description = 'Selesaikan masa percobaan, terbitkan tagihan langganan, tandai tunggakan, dan tangguhkan setelah masa tenggang';

    public function handle(ProcessSubscriptions $process): int
    {
        $failed = 0;
        $total = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value])
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($process, &$failed, &$total) {
                $total++;

                try {
                    $process->handle($tenant);
                } catch (\Throwable $e) {
                    // Satu tenant gagal tidak boleh menghentikan tenant lain; putaran berikutnya mengulang.
                    $failed++;
                    report($e);
                    $this->error("Tenant {$tenant->id} gagal: ".$e::class);
                }
            });

        $this->info("Tenant diproses: $total. Gagal: $failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
