<?php

namespace App\Services\Reports;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingClock;

/**
 * Hitungan untuk lonceng notifikasi di kepala semua halaman pemilik. Hanya angka (tanpa nama atau data
 * pribadi) dan memakai query yang sama dengan dashboard, jadi lonceng dan dashboard tidak pernah berbeda.
 * Bagian keuangan bernilai null bagi pengguna tanpa izin finance.view. Semua query lewat model bertenant.
 */
class OwnerNotifications
{
    public function __construct(
        private ArrearsReport $arrears,
        private ExpiringLeasesReport $expiring,
        private DashboardWidgets $widgets,
    ) {}

    /**
     * @return array{
     *     pending_proofs: int|null,
     *     overdue_invoices: array{count: int, amount: int}|null,
     *     open_complaints: int,
     *     expiring_leases: array{count: int, within_days: int},
     *     quota: array{limit: int, remaining: int},
     * }
     */
    public function for(User $user, Tenant $tenant): array
    {
        $today = BillingClock::today();
        $finance = $user->can('view-finance');
        $quota = $this->widgets->quota($tenant);

        return [
            'pending_proofs' => $finance ? $this->widgets->pendingProofCount() : null,
            'overdue_invoices' => $finance ? $this->arrears->totals($today) : null,
            'open_complaints' => $this->widgets->openComplaintCount(),
            'expiring_leases' => [
                'count' => $this->expiring->count($today),
                'within_days' => ExpiringLeasesReport::WITHIN_DAYS,
            ],
            'quota' => ['limit' => $quota['limit'], 'remaining' => $quota['remaining']],
        ];
    }
}
