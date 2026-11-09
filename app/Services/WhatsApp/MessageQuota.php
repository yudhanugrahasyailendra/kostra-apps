<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;

/**
 * Kuota pesan bulanan (NTF-04). Batas dari plans.monthly_message_quota, pemakaian di usage_counters.
 * Penaikan penghitung berupa satu UPDATE bersyarat (used = used + 1 hanya bila used < batas), jadi
 * pengiriman paralel tidak pernah melewati batas walau tiap proses membaca nilai lama.
 */
class MessageQuota
{
    public const METRIC = 'whatsapp_messages';

    /** Periode berjalan menurut zona waktu tenant (kuota reset tiap tanggal 1). */
    public function period(Tenant $tenant): string
    {
        return BillingClock::today($tenant)->format('Y-m');
    }

    public function limit(Tenant $tenant): int
    {
        return (int) ($tenant->plan?->monthly_message_quota ?? 0);
    }

    public function used(Tenant $tenant): int
    {
        return TenantContext::instance()->runAs($tenant, fn () => (int) UsageCounter::query()
            ->where('metric', self::METRIC)->where('period', $this->period($tenant))->value('used'));
    }

    public function remaining(Tenant $tenant): int
    {
        return max(0, $this->limit($tenant) - $this->used($tenant));
    }

    /** Ambil satu jatah. False bila kuota habis (atau paket tanpa kuota); tidak melempar. */
    public function reserve(Tenant $tenant): bool
    {
        $limit = $this->limit($tenant);

        if ($limit <= 0) {
            return false;
        }

        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $limit) {
            $period = $this->period($tenant);

            // Baris dibuat sekali; insertOrIgnore aman bila dua proses membuatnya bersamaan (unique tenant, metric, period).
            UsageCounter::query()->insertOrIgnore([
                'tenant_id' => $tenant->id, 'metric' => self::METRIC, 'period' => $period, 'used' => 0,
            ]);

            return UsageCounter::query()
                ->where('metric', self::METRIC)->where('period', $period)
                ->where('used', '<', $limit)
                ->increment('used') === 1;
        });
    }

    /** Kembalikan satu jatah untuk pesan yang akhirnya gagal terkirim. Tidak pernah di bawah nol. */
    public function release(Tenant $tenant, ?string $period = null): void
    {
        TenantContext::instance()->runAs($tenant, function () use ($tenant, $period) {
            UsageCounter::query()
                ->where('metric', self::METRIC)->where('period', $period ?? $this->period($tenant))
                ->where('used', '>', 0)
                ->decrement('used');
        });
    }
}
