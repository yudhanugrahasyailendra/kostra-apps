<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * "Hari ini" menurut zona waktu tenant. Database dan server memakai UTC, tetapi tanggal terbit dan
 * jatuh tempo ditentukan oleh hari di tempat kos berada.
 */
class BillingClock
{
    public static function today(?Tenant $tenant = null): CarbonImmutable
    {
        $tenant ??= TenantContext::instance()->tenant();
        $timezone = $tenant?->timezone ?: config('kostra.display_timezone');

        // Tanggal saja (00:00) agar perbandingan dengan kolom date tidak terpengaruh jam.
        return CarbonImmutable::createFromFormat('Y-m-d', CarbonImmutable::now($timezone)->toDateString(), 'UTC')->startOfDay();
    }
}
