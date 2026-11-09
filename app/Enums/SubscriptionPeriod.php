<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum SubscriptionPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    /** Akhir periode (inklusif) yang dimulai pada $start: 1 Nov + sebulan berakhir 30 Nov. */
    public function endFrom(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $start->addMonthNoOverflow()->subDay(),
            self::Yearly => $start->addYearNoOverflow()->subDay(),
        };
    }
}
