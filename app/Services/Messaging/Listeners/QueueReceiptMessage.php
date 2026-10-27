<?php

namespace App\Services\Messaging\Listeners;

use App\Actions\Messaging\QueueWhatsAppMessage;
use App\Enums\MessageTemplateKey;
use App\Events\PaymentRecorded;
use App\Models\Payment;
use App\Services\Messaging\MessageSubjects;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembayaran tercatat (langsung atau lewat persetujuan bukti) -> mengantrekan pesan kuitansi.
 */
class QueueReceiptMessage
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QueueWhatsAppMessage $queue,
        private readonly MessageSubjects $subjects,
    ) {}

    public function handle(PaymentRecorded $event): void
    {
        try {
            $this->context->run($event->tenantId, function () use ($event) {
                $payment = Payment::query()->with('resident')->find($event->paymentId);

                if ($payment === null || $payment->resident === null) {
                    return;
                }

                $context = $this->subjects->payment($payment);

                ($this->queue)(MessageTemplateKey::Receipt, $payment->resident, $payment, $context['values']);
            });
        } catch (Throwable $e) {
            // Hanya nama kelas galat: pesan galat database memuat binding (nomor telepon dan isi pesan).
            Log::warning('Pesan WhatsApp tidak dapat diantrekan.', [
                'exception' => $e::class,
                'tenant_id' => $event->tenantId,
                'payment_id' => $event->paymentId,
            ]);
        }
    }
}
