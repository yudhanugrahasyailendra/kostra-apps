<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\MessageTemplateKey;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Services\WhatsApp\MessageTemplateRenderer;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SendInvoiceReminders
{
    public function __construct(private QueueWhatsAppMessage $queue) {}

    /**
     * Antrekan pengingat tagihan untuk satu tenant (NTF-03). Untuk tiap offset di billing_settings.reminder_offsets,
     * tagihan yang belum lunas dengan due_date = hari ini - offset mendapat template sesuai tandanya (sebelum, tepat,
     * sesudah jatuh tempo). Aman dijalankan berulang: satu tagihan tidak diingatkan dua kali dengan template yang
     * sama pada hari yang sama. Karena tiap offset jatuh di hari berbeda untuk tagihan yang sama, pemeriksaan
     * (tagihan, template, hari) setara dengan (tagihan, template, offset) tanpa kolom atau indeks tambahan.
     *
     * @return int jumlah pesan yang diantrekan atau dicatat dilewati pada putaran ini
     */
    public function handle(Tenant $tenant, ?CarbonInterface $today = null): int
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $today) {
            $today = CarbonImmutable::parse(($today ?? BillingClock::today($tenant))->toDateString());
            $timezone = $tenant->timezone ?: config('kostra.display_timezone');
            $dayStart = CarbonImmutable::parse($today->toDateString(), $timezone)->startOfDay()->utc();
            $dayEnd = $dayStart->addDay();
            $count = 0;

            foreach (array_unique(array_map('intval', BillingSetting::current()->reminder_offsets ?? [])) as $offset) {
                $key = MessageTemplateKey::forReminderOffset($offset);
                $templateIds = $this->templateIds($key);

                if ($templateIds === []) {
                    continue;
                }

                Invoice::query()
                    ->whereIn('status', InvoiceStatus::openValues())
                    ->whereDate('due_date', $today->subDays($offset)->toDateString())
                    ->whereNotNull('resident_id')
                    ->with('resident')
                    ->chunkById(100, function ($invoices) use ($key, $templateIds, $dayStart, $dayEnd, &$count) {
                        foreach ($invoices as $invoice) {
                            $count += $this->remind($invoice, $key, $templateIds, $dayStart, $dayEnd);
                        }
                    });
            }

            return $count;
        });
    }

    /** @param  list<int>  $templateIds */
    private function remind(Invoice $invoice, MessageTemplateKey $key, array $templateIds, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): int
    {
        return DB::transaction(function () use ($invoice, $key, $templateIds, $dayStart, $dayEnd) {
            // Kunci baris tagihan supaya dua putaran bersamaan tidak sama-sama lolos pemeriksaan ganda.
            $locked = Invoice::query()->lockForUpdate()->with('resident')->find($invoice->id);

            if ($locked === null || ! in_array($locked->status, InvoiceStatus::open(), true) || $locked->resident === null) {
                return 0;
            }

            $already = MessageLog::query()
                ->where('subject_type', $locked->getMorphClass())->where('subject_id', $locked->id)
                ->whereIn('template_id', $templateIds)
                ->where('created_at', '>=', $dayStart)->where('created_at', '<', $dayEnd)
                ->exists();

            if ($already) {
                return 0;
            }

            return $this->queue->handle($key, $locked->resident, [
                'jumlah' => MessageTemplateRenderer::formatRupiah($locked->outstanding()),
                'jatuh_tempo' => $locked->due_date->locale('id')->isoFormat('D MMMM Y'),
                'link' => '',
            ], $locked) === null ? 0 : 1;
        });
    }

    /**
     * Id template (sistem dan milik tenant) untuk key ini, supaya pemeriksaan ganda tetap berlaku
     * bila pemilik mengganti template di tengah hari.
     *
     * @return list<int>
     */
    private function templateIds(MessageTemplateKey $key): array
    {
        $tenantId = TenantContext::instance()->id();

        // Alasan unscoped: template sistem bertenant_id null; difilter manual ke baris sistem atau milik tenant ini.
        return TenantContext::instance()->unscoped(fn () => MessageTemplate::query()
            ->where('key', $key->value)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->pluck('id')->map(fn ($id) => (int) $id)->all());
    }
}
