<?php

namespace App\Actions\Messaging;

use App\Actions\Concerns\ResolvesTenant;
use App\Contracts\WhatsAppProvider;
use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\TenantStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\BillingSetting;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Resident;
use App\Services\Messaging\MessageTemplateRenderer;
use App\Services\Messaging\SendWindow;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class QueueWhatsAppMessage
{
    use ResolvesTenant;

    public function __construct(
        private readonly MessageTemplateRenderer $renderer,
        private readonly SendWindow $window,
        private readonly WhatsAppProvider $provider,
    ) {}

    /**
     * Mencatat pesan di message_logs lalu mengantrekannya. Tidak mengirim apa pun secara langsung.
     *
     * Mengembalikan null bila tidak ada pesan yang perlu dicatat: template tidak ada atau dinonaktifkan pemilik,
     * atau akun tenant tidak aktif. Penghuni yang berhenti menerima pesan dicatat sebagai skipped (opted_out).
     *
     * @param  array<string, string>  $values  nilai variabel (lihat MessageSubjects)
     */
    public function __invoke(MessageTemplateKey $key, Resident $resident, Model $subject, array $values): ?MessageLog
    {
        $tenant = $this->currentTenant();

        if (! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
            return null;
        }

        $template = MessageTemplate::resolve($key);

        if ($template === null || ! $template->is_active) {
            return null;
        }

        $recipient = PhoneNumber::normalize($resident->phone);

        $attributes = [
            'template_id' => $template->getKey(),
            'resident_id' => $resident->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'channel' => MessageTemplate::CHANNEL,
            'category' => MessageTemplate::CATEGORY,
            'recipient' => $recipient ?? '',
            'body' => $this->renderer->render($template->body, $values),
            'provider' => $this->provider->name(),
        ];

        if ($recipient === null) {
            return MessageLog::create($attributes + [
                'status' => MessageStatus::Failed,
                'error_code' => 'invalid_recipient',
                'error_message' => 'Nomor WhatsApp penghuni tidak valid.',
                'failed_at' => now(),
            ]);
        }

        if ($resident->whatsapp_opt_out_at !== null) {
            return MessageLog::create($attributes + [
                'status' => MessageStatus::Skipped,
                'skip_reason' => MessageSkipReason::OptedOut,
            ]);
        }

        $settings = BillingSetting::current();
        $now = now()->toImmutable();
        $sendAt = $this->window->nextAllowed($tenant, $settings->send_window_start, $settings->send_window_end, $now);

        $log = MessageLog::create($attributes + [
            'status' => MessageStatus::Queued,
            'scheduled_for' => $sendAt,
        ]);

        $job = SendWhatsAppMessage::dispatch($log->getKey(), $tenant->getKey())->afterCommit();

        if ($sendAt->greaterThan($now)) {
            $job->delay($sendAt);
        }

        return $log;
    }
}
