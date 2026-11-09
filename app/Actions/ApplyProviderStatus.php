<?php

namespace App\Actions;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Services\WhatsApp\StatusUpdate;
use App\Tenancy\TenantContext;

class ApplyProviderStatus
{
    public function __construct(private DeliverWhatsAppMessage $deliver) {}

    /**
     * Terapkan status dari webhook penyedia ke message_logs. Webhook tidak membawa tenant, jadi pesan dicari
     * lewat provider_message_id lalu pekerjaan dilanjutkan dalam konteks tenant pemilik pesan.
     * Hanya pesan berstatus sent yang berubah: delivered, atau failed (jatah kuota dikembalikan).
     */
    public function handle(StatusUpdate $update): ?MessageLog
    {
        // Alasan unscoped: pencarian awal lintas tenant satu-satunya lewat id pesan penyedia; selanjutnya terkunci ke tenant log.
        $found = TenantContext::instance()->unscoped(fn () => MessageLog::query()
            ->where('provider_message_id', $update->providerMessageId)->first());

        if ($found === null) {
            return null;
        }

        $tenant = Tenant::query()->find($found->tenant_id);

        return $tenant === null ? null : TenantContext::instance()->runAs($tenant, function () use ($found, $update) {
            $log = MessageLog::query()->find($found->id);

            if ($log === null || $log->status !== MessageStatus::Sent) {
                return $log;
            }

            if ($update->status === MessageStatus::Delivered) {
                $log->forceFill(['status' => MessageStatus::Delivered, 'delivered_at' => now()])->save();
            } elseif ($update->status === MessageStatus::Failed) {
                $log->forceFill(['error_code' => $update->errorCode, 'error_message' => $update->errorMessage]);
                $this->deliver->fail($log);
            }

            return $log;
        });
    }
}
