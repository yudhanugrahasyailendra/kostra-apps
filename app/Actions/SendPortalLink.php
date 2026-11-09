<?php

namespace App\Actions;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\MessageLog;
use App\Models\Resident;
use Illuminate\Validation\ValidationException;

class SendPortalLink
{
    public function __construct(private QueueWhatsAppMessage $queue) {}

    /**
     * Kirim tautan portal baru ke WhatsApp penghuni (membuat dan mengirim ulang). Token dibuat oleh
     * QueueWhatsAppMessage hanya bila pesan benar-benar akan dikirim. Penghuni yang berhenti menerima
     * pesan tidak dikirimi.
     */
    public function handle(Resident $resident): MessageLog
    {
        $log = $this->queue->handle(MessageTemplateKey::PortalLink, $resident, [], $resident);

        if ($log === null) {
            throw ValidationException::withMessages(['portal' => 'Templat pesan tautan portal belum tersedia.']);
        }

        if ($log->status === MessageStatus::Skipped && $log->skip_reason === MessageSkipReason::OptedOut) {
            throw ValidationException::withMessages(['portal' => 'Penghuni ini berhenti menerima pesan WhatsApp.']);
        }

        return $log;
    }
}
