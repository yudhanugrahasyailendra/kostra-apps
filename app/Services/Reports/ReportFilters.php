<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

/** Parameter laporan yang sudah tervalidasi. `asOf` dipakai laporan tunggakan dan okupansi. */
final class ReportFilters
{
    public function __construct(
        public readonly ReportPeriod $period,
        public readonly CarbonImmutable $asOf,
        public readonly ?int $propertyId = null,
        public readonly string $propertyName = 'Semua properti',
        public readonly ?int $categoryId = null,
    ) {}
}
