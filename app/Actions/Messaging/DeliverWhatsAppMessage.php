<?php

namespace App\Actions\Messaging;

use App\Actions\Concerns\ResolvesTenant;
use App\Contracts\WhatsAppProvider;
use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\TenantStatus;
use App\Models\BillingSetting;
use App\Models\MessageLog;
use App\Services\Messaging\DeliveryResult;
use App\Services\Messaging\MessageQuota;
use App\Services\Messaging\OutboundMessage;
use App\Services\Messaging\SendResult;
use App\Services\Messaging\SendWindow;
use App\Services\Portal\PortalLinkResolver;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverWhatsAppMessage
{
    use ResolvesTenant;

    /** Jeda sebelum percobaan ulang ke-2 dan ke-3, dalam detik. */
    public const BACKOFF = [60, 300];

    public function __construct(
        private readonly WhatsAppProvider $provider,
        private readonly MessageQuota $quota,
        private readonly SendWindow $window,
        private readonly PortalLinkResolver $links,
    ) {}

    /**
     * Satu upaya pengiriman untuk satu baris message_logs, dijalankan dalam konteks tenant. Seluruh upaya berada
     * dalam satu transaksi dengan kunci pada barisnya, jadi dua job untuk pesan yang sama tidak bisa mengirim dua kali.
     *
     * Urutan: tenant aktif, penghuni belum berhenti, di dalam jam kirim (kalau tidak, ditunda), kuota atomik,
     * lalu kirim. Kegagalan sementara dicoba ulang sampai MAX_ATTEMPTS, lalu failed dan kuota dikembalikan.
     */
    public function __invoke(int $logId): DeliveryResult
    {
        $tenant = $this->currentTenant();

        return DB::transaction(function () use ($logId, $tenant) {
            $log = MessageLog::query()->lockForUpdate()->find($logId);

            if ($log === null || $log->status !== MessageStatus::Queued) {
                return DeliveryResult::done();
            }

            if (! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
                $this->fail($log, 'tenant_inactive', 'Akun tidak aktif, pesan tidak dikirim.');

                return DeliveryResult::done();
            }

            // Penghuni bisa berhenti menerima pesan setelah pesan diantrekan.
            if ($log->resident === null || $log->resident->whatsapp_opt_out_at !== null) {
                $this->release($log);
                $log->update(['status' => MessageStatus::Skipped, 'skip_reason' => MessageSkipReason::OptedOut, 'counted_in_quota' => false]);

                return DeliveryResult::done();
            }

            $settings = BillingSetting::current();
            $now = now()->toImmutable();
            $sendAt = $this->window->nextAllowed($tenant, $settings->send_window_start, $settings->send_window_end, $now);

            if ($sendAt->greaterThan($now)) {
                $log->update(['scheduled_for' => $sendAt]);

                return DeliveryResult::deferredUntil($sendAt);
            }

            if (! $log->counted_in_quota) {
                if (! $this->quota->reserve($tenant)) {
                    $log->update(['status' => MessageStatus::Skipped, 'skip_reason' => MessageSkipReason::QuotaExceeded]);

                    return DeliveryResult::done();
                }

                $log->counted_in_quota = true;
            }

            try {
                // Tautan portal bertoken dibuat di sini dan hanya ada di pesan yang dikirim, tidak di message_logs.
                $result = $this->provider->send(new OutboundMessage(
                    $log->getKey(),
                    $log->recipient,
                    $this->links->resolve($log->body, $log->resident),
                    $log->template?->provider_template_name,
                ));
            } catch (Throwable $e) {
                // Hanya nama kelas galat yang dicatat: pesan galat bisa memuat nomor atau isi pesan.
                $result = SendResult::failed('provider_exception', class_basename($e));
            }

            $attempts = $log->attempts + 1;

            if ($result->ok) {
                $log->update([
                    'status' => MessageStatus::Sent,
                    'provider' => $this->provider->name(),
                    'provider_message_id' => $result->providerMessageId,
                    'attempts' => $attempts,
                    'sent_at' => now(),
                    'error_code' => null,
                    'error_message' => null,
                ]);

                return DeliveryResult::done();
            }

            $log->attempts = $attempts;

            if (! $result->retryable || $attempts >= MessageLog::MAX_ATTEMPTS) {
                $this->fail($log, $result->errorCode ?? 'send_failed', $result->errorMessage ?? 'Pengiriman gagal.');

                return DeliveryResult::done();
            }

            $log->update([
                'error_code' => $result->errorCode,
                'error_message' => mb_substr((string) $result->errorMessage, 0, 255),
            ]);

            return DeliveryResult::retryIn(self::BACKOFF[$attempts - 1] ?? end(self::BACKOFF));
        });
    }

    /**
     * Menandai gagal permanen dan mengembalikan kuota yang sudah terpakai. Dipakai juga oleh job saat
     * antrean menyerah (di luar jalur normal di atas).
     */
    public function markFailed(int $logId, string $code, string $message): void
    {
        DB::transaction(function () use ($logId, $code, $message) {
            $log = MessageLog::query()->lockForUpdate()->find($logId);

            if ($log !== null && $log->status === MessageStatus::Queued) {
                $this->fail($log, $code, $message);
            }
        });
    }

    private function fail(MessageLog $log, string $code, string $message): void
    {
        $this->release($log);

        $log->update([
            'status' => MessageStatus::Failed,
            'error_code' => mb_substr($code, 0, 50),
            'error_message' => mb_substr($message, 0, 255),
            'failed_at' => now(),
            'counted_in_quota' => false,
        ]);
    }

    private function release(MessageLog $log): void
    {
        if ($log->counted_in_quota) {
            // Kuota dipakai pada upaya pertama, yang berlangsung pada atau sesudah scheduled_for.
            $this->quota->release($this->currentTenant(), $log->scheduled_for ?? $log->created_at);
        }
    }
}
