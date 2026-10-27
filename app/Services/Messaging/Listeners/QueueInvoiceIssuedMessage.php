<?php

namespace App\Services\Messaging\Listeners;

use App\Actions\Messaging\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Events\InvoiceIssued;
use App\Models\Invoice;
use App\Services\Messaging\MessageSubjects;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tagihan terbit -> mengantrekan pesan tagihan. Event dikirim setelah transaksi penerbitan selesai, jadi galat di sini
 * tidak boleh menggagalkan penerbitan: dilaporkan, bukan dilempar.
 */
class QueueInvoiceIssuedMessage
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QueueWhatsAppMessage $queue,
        private readonly MessageSubjects $subjects,
    ) {}

    public function handle(InvoiceIssued $event): void
    {
        try {
            $this->context->run($event->tenantId, function () use ($event) {
                $invoice = Invoice::query()->with('resident')->find($event->invoiceId);

                if ($invoice === null || $invoice->resident === null) {
                    return;
                }

                $context = $this->subjects->invoice($invoice);

                ($this->queue)(MessageTemplateKey::InvoiceIssued, $invoice->resident, $invoice, $context['values']);
            });
        } catch (Throwable $e) {
            // Hanya nama kelas galat: pesan galat database memuat binding (nomor telepon dan isi pesan).
            Log::warning('Pesan WhatsApp tidak dapat diantrekan.', [
                'exception' => $e::class,
                'tenant_id' => $event->tenantId,
                'invoice_id' => $event->invoiceId,
            ]);
        }
    }
}
