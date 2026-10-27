<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ReportRequest;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\Reports\ArrearsReport;
use App\Services\Reports\CashFlowReport;
use App\Services\Reports\IncomeReport;
use App\Services\Reports\OccupancyReport;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportTable;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /** Kunci URL => judul tab. */
    public const REPORTS = [
        'pemasukan' => 'Pemasukan',
        'tunggakan' => 'Tunggakan',
        'okupansi' => 'Okupansi',
        'rekap' => 'Rekap bulanan',
    ];

    public function index(): RedirectResponse
    {
        Gate::authorize('finance.view');

        return redirect()->route('owner.reports.show', 'pemasukan');
    }

    public function show(
        ReportRequest $request,
        string $report,
        TenantContext $context,
        IncomeReport $income,
        ArrearsReport $arrears,
        OccupancyReport $occupancy,
        CashFlowReport $cashFlow,
        ReportExporter $exporter,
    ): View|Response {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $tenant = $context->current();
        $propertyId = $request->propertyId();
        $period = $request->period($tenant);
        $asOf = $request->asOf($tenant);
        $sort = $request->validated('sort') ?? 'amount';

        $meta = fn (string $periodLabel) => [
            'Usaha' => $tenant->name,
            'Properti' => $propertyId ? (string) Property::query()->whereKey($propertyId)->value('name') : 'Semua properti',
            'Periode' => $periodLabel,
            'Tanggal cetak' => $tenant->today()->locale('id')->translatedFormat('j F Y'),
        ];

        $table = match ($report) {
            'pemasukan' => $income->table($period['from'], $period['to'], $propertyId, $meta($period['label'])),
            'tunggakan' => $arrears->table($tenant->today(), $propertyId, $sort, $meta('Per '.$tenant->today()->locale('id')->translatedFormat('j F Y'))),
            'okupansi' => $occupancy->table($tenant, $asOf, $propertyId, $meta('Per '.$asOf->locale('id')->translatedFormat('j F Y'))),
            'rekap' => $cashFlow->table($period['from']->startOfMonth(), 12, $propertyId, $meta('12 bulan sampai '.$period['from']->locale('id')->translatedFormat('F Y'))),
        };

        return match ($request->validated('format')) {
            'xlsx' => $exporter->xlsx($table),
            'pdf' => $exporter->pdf($table),
            default => $this->screen($report, $table, $request, $tenant, $propertyId, $period, $asOf, $sort),
        };
    }

    private function screen(string $report, ReportTable $table, ReportRequest $request, Tenant $tenant, ?int $propertyId, array $period, CarbonImmutable $asOf, string $sort): View
    {
        return view('owner.reports.show', [
            'report' => $report,
            'reports' => self::REPORTS,
            'table' => $table,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'propertyId' => $propertyId,
            'month' => $period['from']->format('Y-m'),
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
            'asOf' => $asOf->toDateString(),
            'sort' => $sort,
            'query' => $request->validated(),
        ]);
    }
}
