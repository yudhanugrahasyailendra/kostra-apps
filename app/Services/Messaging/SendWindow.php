<?php

namespace App\Services\Messaging;

use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * Jam kirim pesan menurut zona waktu tenant (billing_settings.send_window_start/end). Di luar jam itu pesan
 * ditunda ke awal jam kirim berikutnya, bukan dibuang. Jendela yang melewati tengah malam (mulai > selesai)
 * didukung; mulai = selesai berarti tanpa pembatasan.
 */
class SendWindow
{
    /**
     * Saat paling awal pesan boleh dikirim, dalam UTC: $now bila sudah di dalam jendela.
     */
    public function nextAllowed(Tenant $tenant, string $start, string $end, CarbonImmutable $now): CarbonImmutable
    {
        $timezone = $tenant->timezone ?: config('app.timezone');
        $local = $now->setTimezone($timezone);

        [$startHour, $startMinute] = $this->parse($start);
        [$endHour, $endMinute] = $this->parse($end);

        $startToday = $local->setTime($startHour, $startMinute, 0);
        $endToday = $local->setTime($endHour, $endMinute, 0);

        if ($startToday->equalTo($endToday)) {
            return $now;
        }

        if ($startToday->lessThan($endToday)) {
            return match (true) {
                $local->greaterThanOrEqualTo($startToday) && $local->lessThan($endToday) => $now,
                $local->lessThan($startToday) => $startToday->utc(),
                default => $startToday->addDay()->utc(),
            };
        }

        // Jendela melewati tengah malam: di luar jendela hanya di antara jam selesai dan jam mulai hari ini.
        $inside = $local->greaterThanOrEqualTo($startToday) || $local->lessThan($endToday);

        return $inside ? $now : $startToday->utc();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parse(string $time): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $time) + [1 => 0]);

        return [$hour, $minute];
    }
}
