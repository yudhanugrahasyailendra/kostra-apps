<?php

namespace App\Jobs;

use App\Actions\DeliverWhatsAppMessage;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Mengirim satu pesan dari message_logs (NTF-06). Tenant ditetapkan eksplisit dari tenantId, tidak
 * bergantung pada pengguna login. Penundaan jam kirim dan percobaan ulang memakai release() dengan
 * batas percobaan sendiri di message_logs.attempts; $tries hanya pengaman bila job macet di luar alur itu.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(public int $tenantId, public int $logId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        // Satu pesan tidak diproses dua worker sekaligus.
        return [(new WithoutOverlapping("whatsapp-log-{$this->logId}"))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(DeliverWhatsAppMessage $deliver): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        TenantContext::instance()->runAs($tenant, function () use ($deliver) {
            $log = MessageLog::query()->find($this->logId);

            if ($log === null) {
                return;
            }

            [$outcome, $until] = $deliver->handle($log);

            if (in_array($outcome, [DeliverWhatsAppMessage::RETRY, DeliverWhatsAppMessage::DEFERRED], true) && $until !== null) {
                $this->release(max(1, (int) now()->diffInSeconds($until, true)));
            }
        });
    }

    /** Galat tak terduga yang menghabiskan percobaan antrean: pesan ditandai gagal agar tidak menggantung. */
    public function failed(Throwable $e): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        TenantContext::instance()->runAs($tenant, function () use ($e) {
            $log = MessageLog::query()->find($this->logId);

            if ($log !== null && $log->status->value === 'queued') {
                $log->error_code = 'job_failed';
                // Hanya nama kelas galat; pesan galat bisa memuat nomor atau teks pesan.
                $log->error_message = $e::class;
                app(DeliverWhatsAppMessage::class)->fail($log);
            }
        });
    }
}
