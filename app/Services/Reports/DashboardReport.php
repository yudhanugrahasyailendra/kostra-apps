<?php

namespace App\Services\Reports;

use App\Models\Tenant;
use App\Services\Billing\BillingClock;

/**
 * Angka dashboard pemilik. Tidak punya query sendiri: semuanya dipanggil dari kelas laporan yang sama
 * dengan halaman laporan, sehingga angka dashboard dan laporan periode yang sama tidak pernah berbeda.
 * Bagian keuangan hanya dihitung bila dimintakan (izin finance.view).
 */
class DashboardReport
{
    public function __construct(
        private IncomeReport $income,
        private ArrearsReport $arrears,
        private OccupancyReport $occupancy,
        private ExpiringLeasesReport $expiring,
        private DashboardWidgets $widgets,
    ) {}

    /** @return array<string, mixed> */
    public function build(bool $withFinance, ?Tenant $tenant = null): array
    {
        $today = BillingClock::today();
        $occupancy = $this->occupancy->at($today);

        $data = [
            'as_of' => $today->toDateString(),
            'occupancy' => [
                'total' => $occupancy['total'],
                'occupied' => $occupancy['occupied'],
                'vacant' => $occupancy['vacant'],
                'percent' => $occupancy['percent'],
            ],
            'expiring_leases' => [
                'count' => $this->expiring->count($today),
                'within_days' => ExpiringLeasesReport::WITHIN_DAYS,
                'items' => $this->expiring->rows($today)->values()->all(),
            ],
            'finance' => null,
            'due_soon' => null,
            'pending_proofs' => null,
            'complaints' => $this->widgets->openComplaints(),
            'room_map' => $this->widgets->roomMap(),
            'quota' => $tenant ? $this->widgets->quota($tenant) : null,
        ];

        if ($withFinance) {
            $data['due_soon'] = $this->widgets->dueSoon($today);
            $data['pending_proofs'] = $this->widgets->pendingProofs();

            $period = ReportPeriod::month($today->format('Y-m'));
            $arrears = $this->arrears->summary($today);

            $data['finance'] = [
                'period' => $period->key(),
                'period_label' => $period->label(),
                'income' => $this->income->total($period),
                'income_count' => $this->income->count($period),
                'arrears_total' => $arrears['total'],
                'arrears_count' => $arrears['count'],
                'arrears_buckets' => array_values($arrears['buckets']),
            ];
        }

        return $data;
    }
}
