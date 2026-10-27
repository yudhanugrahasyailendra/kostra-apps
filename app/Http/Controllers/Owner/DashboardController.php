<?php

namespace App\Http\Controllers\Owner;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentProof;
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

        $chart = $canSeeFinance ? $cashFlow->months($month, 6) : [];
        $current = collect($chart)->last();
        $previous = collect($chart)->slice(-2, 1)->first();

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
            'greeting' => match (true) {
                now($tenant->timezone)->hour < 11 => 'Selamat Pagi',
                now($tenant->timezone)->hour < 15 => 'Selamat Siang',
                now($tenant->timezone)->hour < 18 => 'Selamat Sore',
                default => 'Selamat Malam',
            },
            'today' => $today,
            'pendingProofs' => PaymentProof::query()->pending()->with(['invoice:id,number', 'resident:id,name'])->latest('id')->limit(2)->get(),
            'pendingProofCount' => PaymentProof::query()->pending()->count(),
            'openInvoices' => $canSeeFinance || $request->user()->can('viewAny', Invoice::class)
                ? Invoice::query()->whereIn('status', InvoiceStatus::openValues())
                    ->with(['resident:id,name', 'lease.room:id,number'])
                    ->orderBy('due_date')->limit(5)->get()
                : collect(),
            'overdueCount' => Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count(),
            'complaints' => Complaint::query()->open()->with(['room:id,number', 'resident:id,name'])->latest('id')->limit(3)->get(),
            // Bagian keuangan hanya untuk pemilik atau staf berizin finance.view.
            'finance' => $canSeeFinance ? [
                'income' => $income->total($month, $month->endOfMonth()->startOfDay()),
                'arrears' => $arrears->total($today),
                'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
                'chart' => $chart,
                'incomeDelta' => $previous && $previous['income'] > 0 && $current
                    ? round(($current['income'] - $previous['income']) / $previous['income'] * 100, 1)
                    : null,
                'expenseTotal' => $current['expense'] ?? 0,
                'net' => $current['net'] ?? 0,
                'expenseByCategory' => Expense::query()
                    ->whereBetween('spent_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                    ->with('category:id,name')
                    ->selectRaw('expense_category_id, SUM(amount) as total')
                    ->groupBy('expense_category_id')->orderByDesc('total')->limit(3)->get(),
            ] : null,
        ]);
    }
}
