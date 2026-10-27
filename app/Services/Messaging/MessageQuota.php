<?php

namespace App\Services\Messaging;

use App\Models\Tenant;
use App\Models\UsageCounter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kuota pesan bulanan sesuai paket. Pemakaian dinaikkan atomik: satu pernyataan UPDATE bersyarat
 * (used = used + 1 hanya bila used di bawah batas), jadi pengiriman paralel tidak bisa melewati kuota.
 * Periode mengikuti bulan kalender menurut zona waktu tenant.
 */
class MessageQuota
{
    public const METRIC = 'whatsapp_messages';

    public function limit(Tenant $tenant): int
    {
        return (int) ($tenant->plan?->monthly_message_quota ?? 0);
    }

    /**
     * Periode (bulan kalender menurut zona waktu tenant) dari saat $at, atau hari ini bila tidak diberikan.
     */
    public function period(Tenant $tenant, ?CarbonInterface $at = null): string
    {
        if ($at === null) {
            return $tenant->today()->format('Y-m');
        }

        return CarbonImmutable::instance($at)->setTimezone($tenant->timezone ?: config('app.timezone'))->format('Y-m');
    }

    public function used(Tenant $tenant): int
    {
        return (int) UsageCounter::query()
            ->where('metric', self::METRIC)
            ->where('period', $this->period($tenant))
            ->value('used');
    }

    public function remaining(Tenant $tenant): int
    {
        return max(0, $this->limit($tenant) - $this->used($tenant));
    }

    /**
     * Memakai satu kuota. False bila kuota bulan ini sudah habis (termasuk paket tanpa kuota).
     */
    public function reserve(Tenant $tenant): bool
    {
        $limit = $this->limit($tenant);
        $period = $this->period($tenant);

        if ($limit < 1) {
            return false;
        }

        // Baris penghitung dibuat bila belum ada; proses lain yang lebih dulu membuatnya tidak masalah.
        UsageCounter::query()->insertOrIgnore([
            'tenant_id' => $tenant->getKey(),
            'metric' => self::METRIC,
            'period' => $period,
            'used' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return UsageCounter::query()
            ->where('metric', self::METRIC)
            ->where('period', $period)
            ->where('used', '<', $limit)
            ->increment('used') === 1;
    }

    /**
     * Mengembalikan satu kuota (pesan gagal permanen setelah kuota dipakai). Tidak pernah di bawah nol.
     * $reservedAt = saat kuota itu dipakai, supaya pengembalian di pergantian bulan masuk ke bulan yang benar.
     */
    public function release(Tenant $tenant, ?CarbonInterface $reservedAt = null): void
    {
        UsageCounter::query()
            ->where('metric', self::METRIC)
            ->where('period', $this->period($tenant, $reservedAt))
            ->where('used', '>', 0)
            ->decrement('used');
    }
}
