<?php

namespace App\Listeners;

use App\Actions\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Events\ComplaintStatusChanged;
use App\Models\Resident;

/** Memberi tahu penghuni setiap status komplainnya berubah (KMP-02). */
class QueueComplaintStatusMessage extends QueuesWhatsAppNotification
{
    public function __construct(private QueueWhatsAppMessage $queue) {}

    public function handle(ComplaintStatusChanged $event): void
    {
        $complaint = $event->complaint;

        $this->inTenantOf($complaint, function () use ($complaint) {
            $resident = Resident::query()->withTrashed()->find($complaint->resident_id);

            if ($resident === null) {
                return;
            }

            $this->queue->handle(MessageTemplateKey::ComplaintStatus, $resident, ['link' => ''], $complaint);
        });
    }
}
