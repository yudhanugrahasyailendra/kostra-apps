<?php

namespace App\Listeners;

use App\Actions\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Events\PaymentRecorded;
use App\Models\Resident;
use App\Services\WhatsApp\MessageTemplateRenderer;

class QueueReceiptMessage extends QueuesWhatsAppNotification
{
    public function __construct(private QueueWhatsAppMessage $queue) {}

    public function handle(PaymentRecorded $event): void
    {
        $payment = $event->payment;

        $this->inTenantOf($payment, function () use ($payment) {
            $resident = Resident::query()->withTrashed()->find($payment->resident_id);

            if ($resident === null) {
                return;
            }

            $this->queue->handle(MessageTemplateKey::Receipt, $resident, [
                'jumlah' => MessageTemplateRenderer::formatRupiah((int) $payment->amount),
                'link' => '',
            ], $payment);
        });
    }
}
