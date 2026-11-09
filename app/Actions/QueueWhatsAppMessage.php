<?php

namespace App\Actions;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Jobs\SendWhatsAppMessage;
use App\Models\BillingSetting;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Resident;
use App\Services\WhatsApp\MessageTemplateRenderer;
use App\Services\WhatsApp\SendWindow;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class QueueWhatsAppMessage
{
    public function __construct(
        private MessageTemplateRenderer $renderer,
        private SendWindow $window,
        private WhatsAppProvider $provider,
    ) {}

    /**
     * Render template menjadi pesan, catat di message_logs, lalu antrekan pengirimannya (NTF-06).
     * Harus dipanggil di dalam konteks tenant. Mengembalikan null bila tenant tidak punya template aktif
     * untuk key itu. Penghuni yang berhenti menerima pesan dicatat `skipped` (opted_out) tanpa antrean.
     * Di luar jam kirim pesan tetap diantrekan dan baru dikirim pada jam kirim berikutnya (scheduled_for).
     *
     * @param  array<string, string|int|null>  $values  nilai variabel selain {{nama}}
     */
    public function handle(MessageTemplateKey $key, Resident $resident, array $values = [], ?Model $subject = null): ?MessageLog
    {
        $tenant = TenantContext::instance()->tenant();

        if ($tenant === null || (int) $resident->tenant_id !== $tenant->id) {
            return null;
        }

        $template = MessageTemplate::effective($key);

        if ($template === null) {
            return null;
        }

        $log = new MessageLog([
            'template_id' => $template->id,
            'resident_id' => $resident->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'channel' => $template->channel,
            'category' => $template->category,
            'recipient' => $resident->phone,
            'body' => $this->renderer->render($template->body, ['nama' => $resident->name] + $values),
            'status' => MessageStatus::Queued,
            'provider' => $this->provider->name(),
            'attempts' => 0,
            'counted_in_quota' => false,
        ]);

        if ($resident->whatsapp_opt_out_at !== null) {
            $log->status = MessageStatus::Skipped;
            $log->skip_reason = MessageSkipReason::OptedOut;
            $log->save();

            return $log;
        }

        $opening = $this->window->nextOpening($tenant, BillingSetting::current(), now());
        $log->scheduled_for = $opening ?? now();
        $log->save();

        $dispatch = SendWhatsAppMessage::dispatch($tenant->id, $log->id)->afterCommit();

        if ($opening !== null) {
            $dispatch->delay($opening);
        }

        return $log;
    }
}
