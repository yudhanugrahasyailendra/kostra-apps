<?php

namespace App\Actions;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Models\BillingSetting;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Resident;
use App\Services\WhatsApp\MessageQuota;
use App\Services\WhatsApp\OutboundMessage;
use App\Services\WhatsApp\SendWindow;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeliverWhatsAppMessage
{
    public const SENT = 'sent';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    /** Dicoba lagi nanti ($until diisi). */
    public const RETRY = 'retry';

    /** Ditunda ke jam kirim berikutnya ($until diisi). */
    public const DEFERRED = 'deferred';

    /** Pesan sudah tidak antre (diproses job lain); tidak ada yang dikerjakan. */
    public const IGNORED = 'ignored';

    public function __construct(
        private WhatsAppProvider $provider,
        private SendWindow $window,
        private MessageQuota $quota,
    ) {}

    /**
     * Satu upaya pengiriman, dalam konteks tenant milik pesan. Urutan pemeriksaan: opt-out, jam kirim,
     * kuota (atomik), lalu penyedia. Percobaan ulang dibatasi `kostra.whatsapp.max_attempts`; setelah itu
     * pesan berstatus failed dengan kode galat dan jatah kuotanya dikembalikan.
     *
     * @return array{0: string, 1: ?CarbonImmutable} hasil (konstanta di kelas ini) dan waktu coba berikutnya
     */
    public function handle(MessageLog $log): array
    {
        $tenant = TenantContext::instance()->tenant();
        abort_if($tenant === null || (int) $log->tenant_id !== $tenant->id, 500, 'Pengiriman pesan harus dalam konteks tenant pemiliknya.');

        $log = DB::transaction(fn () => MessageLog::query()->lockForUpdate()->find($log->id));

        if ($log === null || $log->status !== MessageStatus::Queued) {
            return [self::IGNORED, null];
        }

        $resident = $log->resident_id ? Resident::query()->withTrashed()->find($log->resident_id) : null;

        if ($resident?->whatsapp_opt_out_at !== null) {
            return $this->skip($log, MessageSkipReason::OptedOut);
        }

        $opening = $this->window->nextOpening($tenant, BillingSetting::current(), now());

        if ($opening !== null) {
            $log->update(['scheduled_for' => $opening]);

            return [self::DEFERRED, $opening];
        }

        if (! $log->counted_in_quota) {
            if (! $this->quota->reserve($tenant)) {
                return $this->skip($log, MessageSkipReason::QuotaExceeded);
            }

            $log->update(['counted_in_quota' => true]);
        }

        $log->attempts++;

        $result = $this->provider->send(new OutboundMessage(
            $log->recipient,
            $log->body,
            // Alasan unscoped: template sistem bertenant_id null; dibatasi ke baris sistem atau milik tenant pesan.
            TenantContext::instance()->unscoped(fn () => MessageTemplate::query()
                ->whereKey($log->template_id)
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $log->tenant_id))
                ->value('provider_template_name')),
            $log->id,
        ));

        if ($result->ok) {
            $log->forceFill([
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
                'provider_message_id' => $result->providerMessageId,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return [self::SENT, null];
        }

        $log->forceFill(['error_code' => $result->errorCode, 'error_message' => $result->errorMessage]);

        if ($result->retryable && $log->attempts < (int) config('kostra.whatsapp.max_attempts')) {
            $log->save();
            $backoff = config('kostra.whatsapp.retry_backoff_seconds');
            $seconds = (int) ($backoff[min($log->attempts, count($backoff)) - 1] ?? end($backoff));

            return [self::RETRY, CarbonImmutable::now()->addSeconds($seconds)];
        }

        $this->fail($log);

        return [self::FAILED, null];
    }

    /** Tandai gagal permanen dan kembalikan jatah kuota yang sudah diambil. */
    public function fail(MessageLog $log): void
    {
        $refund = $log->counted_in_quota;

        $log->forceFill(['status' => MessageStatus::Failed, 'failed_at' => now(), 'counted_in_quota' => false])->save();

        if ($refund && ($tenant = TenantContext::instance()->tenant()) !== null) {
            $this->quota->release($tenant);
        }
    }

    /** @return array{0: string, 1: null} */
    private function skip(MessageLog $log, MessageSkipReason $reason): array
    {
        $log->forceFill(['status' => MessageStatus::Skipped, 'skip_reason' => $reason])->save();

        return [self::SKIPPED, null];
    }
}
