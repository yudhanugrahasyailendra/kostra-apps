<?php

namespace Tests\Concerns;

use App\Actions\Messaging\DeliverWhatsAppMessage;
use App\Actions\Messaging\QueueWhatsAppMessage;
use App\Contracts\WhatsAppProvider;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\Plan;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Services\Messaging\DeliveryResult;
use App\Services\Messaging\FakeWhatsAppProvider;
use App\Services\Messaging\MessageSubjects;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Collection;

/**
 * Fixture pesan WhatsApp di atas fixture keuangan. Penyedia selalu "fake": tidak ada pesan nyata yang dikirim.
 * Antrean tes bersifat sync; tes yang butuh mengendalikan waktu pengiriman memakai Queue::fake().
 */
trait MessagingFixtures
{
    use FinanceFixtures;

    protected function setUpMessaging(string $today = '2026-10-15', string $plan = 'business'): void
    {
        $this->setUpFinance($today, $plan);
        $this->seed(MessageTemplateSeeder::class);
    }

    protected function provider(): FakeWhatsAppProvider
    {
        $provider = app(WhatsAppProvider::class);
        $this->assertInstanceOf(FakeWhatsAppProvider::class, $provider);

        return $provider;
    }

    /**
     * Mengatur kuota pesan paket (dipanggil sebelum ada pesan).
     */
    protected function setQuota(int $quota, string $plan = 'business'): void
    {
        Plan::query()->where('key', $plan)->update(['monthly_message_quota' => $quota]);
        $this->tenant->unsetRelation('plan');
    }

    /**
     * @return Collection<int, MessageLog>
     */
    protected function logs(?Tenant $tenant = null): Collection
    {
        return $this->asTenant($tenant ?? $this->tenant, fn () => MessageLog::with('template')->orderBy('id')->get());
    }

    protected function freshLog(MessageLog $log): MessageLog
    {
        return $this->asTenant($this->tenant, fn () => MessageLog::findOrFail($log->getKey()));
    }

    /**
     * Mencatat pesan tagihan lewat Action (jalur yang sama dengan pendengar event).
     */
    protected function queueInvoiceMessage(Invoice $invoice, MessageTemplateKey $key = MessageTemplateKey::InvoiceIssued, ?Tenant $tenant = null): ?MessageLog
    {
        return $this->asTenant($tenant ?? $this->tenant, function () use ($invoice, $key) {
            $invoice = Invoice::with('resident')->findOrFail($invoice->getKey());
            $context = app(MessageSubjects::class)->invoice($invoice);

            return app(QueueWhatsAppMessage::class)($key, $invoice->resident, $invoice, $context['values']);
        });
    }

    /**
     * Satu upaya pengiriman untuk satu log, seperti yang dilakukan job.
     */
    protected function deliver(MessageLog $log, ?Tenant $tenant = null): DeliveryResult
    {
        return $this->asTenant($tenant ?? $this->tenant, fn () => app(DeliverWhatsAppMessage::class)($log->getKey()));
    }

    protected function residentOf(Invoice $invoice): Resident
    {
        return $this->asTenant($this->tenant, fn () => Resident::findOrFail($invoice->resident_id));
    }

    protected function usage(?Tenant $tenant = null): int
    {
        $tenant ??= $this->tenant;

        return (int) $this->asTenant($tenant, fn () => UsageCounter::query()->where('metric', 'whatsapp_messages')->value('used'));
    }

    /**
     * @return list<string>
     */
    protected function statuses(): array
    {
        return $this->logs()->map(fn (MessageLog $log) => $log->status instanceof MessageStatus ? $log->status->value : $log->status)->all();
    }
}
