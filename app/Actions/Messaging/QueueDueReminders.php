<?php

namespace App\Actions\Messaging;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\InvoiceStatus;
use App\Enums\MessageTemplateKey;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Services\Messaging\MessageSubjects;

class QueueDueReminders
{
    use ResolvesTenant;

    public function __construct(
        private readonly QueueWhatsAppMessage $queue,
        private readonly MessageSubjects $subjects,
    ) {}

    /**
     * Pengingat tagihan menurut billing_settings.reminder_offsets (hari relatif jatuh tempo: -3, 0, 3, ...).
     * Hanya tagihan yang masih punya sisa dan belum batal. Satu tagihan tidak diingatkan dua kali untuk template
     * yang sama pada hari yang sama, jadi menjalankan ulang penjadwal aman (diperiksa lewat message_logs).
     *
     * @return int jumlah pesan yang dicatat
     */
    public function __invoke(): int
    {
        $tenant = $this->currentTenant();
        $today = $tenant->today();
        $count = 0;

        $offsets = collect(BillingSetting::current()->reminder_offsets ?? [])
            ->filter(fn ($offset) => is_int($offset) || (is_string($offset) && preg_match('/^-?\d+$/', $offset) === 1))
            ->map(fn ($offset) => (int) $offset)
            ->unique();

        foreach ($offsets as $offset) {
            $key = match (true) {
                $offset < 0 => MessageTemplateKey::ReminderBeforeDue,
                $offset === 0 => MessageTemplateKey::ReminderDueToday,
                default => MessageTemplateKey::ReminderOverdue,
            };

            // Pengingat hari ini untuk offset N berlaku bagi tagihan yang jatuh temponya hari ini dikurangi N.
            $invoices = Invoice::query()
                ->whereIn('status', InvoiceStatus::openValues())
                ->whereColumn('total', '>', 'paid_total')
                ->where('due_date', $today->subDays($offset)->toDateString())
                ->with('resident')
                ->get();

            foreach ($invoices as $invoice) {
                if ($this->alreadySentToday($invoice, $key) || $invoice->resident === null) {
                    continue;
                }

                $context = $this->subjects->invoice($invoice);

                if (($this->queue)($key, $invoice->resident, $invoice, $context['values']) !== null) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function alreadySentToday(Invoice $invoice, MessageTemplateKey $key): bool
    {
        $start = $this->currentTenant()->today()->utc();

        return MessageLog::query()
            ->where('subject_type', $invoice->getMorphClass())
            ->where('subject_id', $invoice->getKey())
            ->whereHas('template', fn ($query) => $query->where('key', $key->value))
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->addDay())
            ->exists();
    }
}
