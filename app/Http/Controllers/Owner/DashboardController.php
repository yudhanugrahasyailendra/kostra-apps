<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Room;
use App\Services\Reports\ArrearsReport;
use App\Services\Reports\CashFlowReport;
use App\Services\Reports\IncomeReport;
use App\Services\Reports\OccupancyReport;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Semua angka berasal dari kelas query laporan (app/Services/Reports) yang sama dengan halaman laporan,
     * jadi dashboard dan laporan untuk periode yang sama tidak pernah berbeda.
     */
    public function __invoke(
        Request $request,
        TenantContext $context,
        OccupancyReport $occupancy,
        IncomeReport $income,
        ArrearsReport $arrears,
        CashFlowReport $cashFlow,
    ): View {
        $tenant = $context->current();
        $today = $tenant->today();

        $rooms = Room::query()
            ->with(['property:id,name', 'roomType:id,name', 'activeLease.primaryResident:id,name'])
            ->orderBy('property_id')
            ->orderBy('floor')
            ->orderByRaw('LENGTH(number)')
            ->orderBy('number')
            ->limit(300)
            ->get();

        $canSeeFinance = $request->user()->can('finance.view');
        $month = $today->startOfMonth();

        return view('owner.dashboard', [
            // Denah: properti, lalu lantai.
            'floorPlan' => $rooms->groupBy(fn (Room $room) => $room->property->name)
                ->map(fn ($group) => $group->groupBy(fn (Room $room) => $room->floor ?? 0)),
            'occupancy' => $occupancy->onDate($tenant, $today),
            'expiring' => Lease::query()->expiringWithin(Lease::EXPIRING_SOON_DAYS, $today)
                ->with(['room:id,number', 'primaryResident:id,name'])
                ->orderBy('end_date')->limit(5)->get(),
            'expiringCount' => Lease::query()->expiringWithin(Lease::EXPIRING_SOON_DAYS, $today)->count(),
            'expiringDays' => Lease::EXPIRING_SOON_DAYS,
            // Bagian keuangan hanya untuk pemilik atau staf berizin finance.view.
            'finance' => $canSeeFinance ? [
                'income' => $income->total($month, $month->endOfMonth()->startOfDay()),
                'arrears' => $arrears->total($today),
                'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
                'chart' => $cashFlow->months($month, 6),
            ] : null,
        ]);
    }
}
