<?php

namespace App\Services\WhatsApp;

use App\Models\BillingSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jam kirim pesan (NTF-08), dibaca dalam zona waktu tenant dari billing_settings. Di luar jam ini pesan
 * ditunda ke awal jam kirim berikutnya. Jendela yang melewati tengah malam (mulai 20:00, selesai 06:00)
 * didukung; mulai sama dengan selesai berarti sepanjang hari.
 */
class SendWindow
{
    /** Null bila $now berada di dalam jam kirim; selain itu awal jam kirim berikutnya (UTC). */
    public function nextOpening(Tenant $tenant, BillingSetting $settings, CarbonInterface $now): ?CarbonImmutable
    {
        $timezone = $tenant->timezone ?: config('kostra.display_timezone');
        $local = CarbonImmutable::instance($now)->setTimezone($timezone);

        [$startH, $startM] = $this->parse((string) $settings->send_window_start);
        [$endH, $endM] = $this->parse((string) $settings->send_window_end);

        $minute = $local->hour * 60 + $local->minute;
        $start = $startH * 60 + $startM;
        $end = $endH * 60 + $endM;

        $inside = match (true) {
            $start === $end => true,
            $start < $end => $minute >= $start && $minute < $end,
            default => $minute >= $start || $minute < $end,
        };

        if ($inside) {
            return null;
        }

        $opening = $local->setTime($startH, $startM);

        if ($opening->lte($local)) {
            $opening = $opening->addDay();
        }

        return $opening->setTimezone('UTC');
    }

    /** @return array{0: int, 1: int} */
    private function parse(string $time): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);

        return [$hour, $minute];
    }
}
