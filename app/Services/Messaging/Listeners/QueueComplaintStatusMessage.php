<?php

namespace App\Services\Messaging\Listeners;

use App\Actions\Messaging\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Events\ComplaintStatusChanged;
use App\Models\Complaint;
use App\Services\Messaging\MessageSubjects;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Status komplain berubah -> mengantrekan pesan ke penghuni pengaju (KMP-02).
 */
class QueueComplaintStatusMessage
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QueueWhatsAppMessage $queue,
        private readonly MessageSubjects $subjects,
    ) {}

    public function handle(ComplaintStatusChanged $event): void
    {
        try {
            $this->context->run($event->tenantId, function () use ($event) {
                $complaint = Complaint::query()->with('resident')->find($event->complaintId);

                if ($complaint === null || $complaint->resident === null) {
                    return;
                }

                $context = $this->subjects->complaint($complaint);

                ($this->queue)(MessageTemplateKey::ComplaintStatus, $complaint->resident, $complaint, $context['values']);
            });
        } catch (Throwable $e) {
            // Hanya nama kelas galat: pesan galat database memuat binding (nomor telepon dan isi pesan).
            Log::warning('Pesan WhatsApp tidak dapat diantrekan.', [
                'exception' => $e::class,
                'tenant_id' => $event->tenantId,
                'complaint_id' => $event->complaintId,
            ]);
        }
    }
}
