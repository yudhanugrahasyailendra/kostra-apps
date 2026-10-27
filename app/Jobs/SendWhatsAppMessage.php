<?php

namespace App\Jobs;

use App\Actions\Messaging\DeliverWhatsAppMessage;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Mengirim SATU pesan dari message_logs. Konteks tenant ditetapkan eksplisit dari id di payload. Pesan di luar
 * jam kirim atau yang gagal sementara dikembalikan ke antrean (release) dengan jeda; jumlah percobaan dibatasi
 * oleh message_logs.attempts (bukan oleh $tries antrean, karena penundaan ke jam kirim juga memakai release).
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $logId,
        public readonly int $tenantId,
    ) {}

    /**
     * Batas waktu mutlak: pesan yang tertunda lebih dari dua hari (mis. akhir pekan dengan jam kirim sempit) menyerah.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(2);
    }

    public function handle(TenantContext $context, DeliverWhatsAppMessage $deliver): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $result = $context->run($tenant, fn () => $deliver($this->logId));

        if (! $result->isDone()) {
            $this->release($result->releaseSeconds);
        }
    }

    /**
     * Antrean menyerah (batas waktu terlewati atau galat tak terduga): pesan ditandai gagal dan kuota dikembalikan.
     * Hanya nama kelas galat yang dicatat; pesan galat bisa memuat data pribadi.
     */
    public function failed(Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => app(DeliverWhatsAppMessage::class)->markFailed(
            $this->logId,
            'job_failed',
            class_basename($exception),
        ));
    }
}
