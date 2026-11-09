<?php

namespace App\Listeners;

use App\Actions\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Events\InvoiceIssued;
use App\Models\Resident;
use App\Services\WhatsApp\MessageTemplateRenderer;

class QueueInvoiceIssuedMessage extends QueuesWhatsAppNotification
{
    public function __construct(private QueueWhatsAppMessage $queue) {}

    public function handle(InvoiceIssued $event): void
    {
        $invoice = $event->invoice;

        $this->inTenantOf($invoice, function () use ($invoice) {
            $resident = $invoice->resident_id ? Resident::query()->withTrashed()->find($invoice->resident_id) : null;

            if ($resident === null) {
                return;
            }

            $this->queue->handle(MessageTemplateKey::InvoiceIssued, $resident, [
                'jumlah' => MessageTemplateRenderer::formatRupiah((int) $invoice->total),
                'jatuh_tempo' => $invoice->due_date->locale('id')->isoFormat('D MMMM Y'),
                'link' => '',
            ], $invoice);
        });
    }
}
