<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Rentang tanggal laporan (kedua ujung termasuk). Tanggal selalu tanpa jam, dalam kalender tenant. */
final class ReportPeriod
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to)
    {
        if ($to->lt($from)) {
            throw new InvalidArgumentException('Akhir periode tidak boleh sebelum awalnya.');
        }
    }

    /** @param  string  $yearMonth  Format Y-m. */
    public static function month(string $yearMonth): self
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $yearMonth.'-01', 'UTC');

        return new self($start->startOfMonth(), $start->endOfMonth()->startOfDay());
    }

    public function key(): string
    {
        return $this->from->format('Y-m');
    }

    public function label(): string
    {
        return $this->from->locale('id')->translatedFormat('F Y');
    }
}
