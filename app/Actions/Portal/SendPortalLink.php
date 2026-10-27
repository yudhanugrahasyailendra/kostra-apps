<?php

namespace App\Actions\Portal;

use App\Actions\Messaging\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Models\MessageLog;
use App\Models\Resident;
use App\Services\Messaging\MessageSubjects;

class SendPortalLink
{
    public function __construct(
        private readonly QueueWhatsAppMessage $queue,
        private readonly MessageSubjects $subjects,
    ) {}

    /**
     * Mengantrekan pesan tautan portal lewat lapisan pesan (kuota, jam kirim, dan opt-out berlaku). Tautan bertoken
     * dibuat saat pesan benar-benar dikirim; message_logs hanya menyimpan placeholder.
     *
     * Null bila tidak ada pesan yang dicatat (template dinonaktifkan atau akun tidak aktif).
     */
    public function __invoke(Resident $resident): ?MessageLog
    {
        return ($this->queue)(MessageTemplateKey::PortalLink, $resident, $resident, $this->subjects->portal($resident));
    }
}
