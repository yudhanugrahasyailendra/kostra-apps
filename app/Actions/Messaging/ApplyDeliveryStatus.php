<?php

namespace App\Actions\Messaging;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Services\Messaging\MessageQuota;
use App\Services\Messaging\StatusUpdate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class ApplyDeliveryStatus
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MessageQuota $quota,
    ) {}

    /**
     * Menerapkan status dari penyedia (hasil WhatsAppProvider::parseStatusWebhook) ke message_logs. Webhook tidak
     * membawa tenant, jadi pesan dicari lewat provider_message_id (terindeks), lalu diproses dalam konteks tenant
     * pemilik pesan itu. Urutan status dijaga: pesan yang sudah delivered tidak mundur.
     */
    public function __invoke(StatusUpdate $update): ?MessageLog
    {
        // Pengecualian tanpa tenant disengaja: konteks tenant baru diketahui setelah pesannya ditemukan.
        $found = $this->context->withoutTenant(
            fn () => MessageLog::query()->where('provider_message_id', $update->providerMessageId)->first(['id', 'tenant_id']),
        );

        if ($found === null) {
            return null;
        }

        return $this->context->run($found->tenant_id, fn () => DB::transaction(function () use ($found, $update) {
            $log = MessageLog::query()->lockForUpdate()->find($found->getKey());

            if ($log === null || $log->status === MessageStatus::Delivered) {
                return $log;
            }

            if ($update->status === StatusUpdate::DELIVERED) {
                $log->update(['status' => MessageStatus::Delivered, 'delivered_at' => now()]);

                return $log;
            }

            // Penyedia menerima pesan tetapi gagal mengantarkannya: tidak terkirim, jadi kuota dikembalikan.
            if ($log->counted_in_quota) {
                $this->quota->release($this->context->current(), $log->sent_at ?? $log->scheduled_for);
            }

            $log->update([
                'status' => MessageStatus::Failed,
                'error_code' => mb_substr($update->errorCode ?? 'delivery_failed', 0, 50),
                'error_message' => mb_substr($update->errorMessage ?? 'Pesan tidak sampai ke penerima.', 0, 255),
                'failed_at' => now(),
                'counted_in_quota' => false,
            ]);

            return $log;
        }));
    }
}
