<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ReportRequest;
use App\Models\Property;
use App\Services\Billing\BillingClock;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportFilters;
use App\Tenancy\TenantContext;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportBuilder $builder): Response
    {
        $type = $request->reportType();
        $filters = $request->filters();

        return Inertia::render('Owner/Reports/Index', [
            'type' => $type,
            'types' => collect(ReportBuilder::TYPES)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'filters' => [
                'period' => $filters->period->key(),
                'date' => $filters->asOf->toDateString(),
                'property_id' => $filters->propertyId,
            ],
            'period_label' => $builder->periodLabel($type, $filters),
            'property_name' => $filters->propertyName,
            'table' => $builder->build($type, $filters)->toArray(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Property $p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'today' => BillingClock::today()->toDateString(),
        ]);
    }

    public function xlsx(ReportRequest $request, ReportBuilder $builder, ReportExporter $exporter): BinaryFileResponse
    {
        $type = $request->reportType();
        $filters = $request->filters();
        $path = $exporter->xlsx($builder->build($type, $filters), $this->meta($builder, $type, $filters));

        return response()->download($path, $this->filename($type, $filters, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function pdf(ReportRequest $request, ReportBuilder $builder, ReportExporter $exporter): HttpResponse
    {
        $type = $request->reportType();
        $filters = $request->filters();

        return $exporter->pdf($builder->build($type, $filters), $this->meta($builder, $type, $filters))
            ->download($this->filename($type, $filters, 'pdf'));
    }

    /** @return array{business: string, property: string, period: string, printed_at: string} */
    private function meta(ReportBuilder $builder, string $type, ReportFilters $filters): array
    {
        $tenant = TenantContext::instance()->tenant();
        $timezone = $tenant?->timezone ?: config('kostra.display_timezone');

        return [
            'business' => (string) $tenant?->name,
            'property' => $filters->propertyName,
            'period' => $builder->periodLabel($type, $filters),
            'printed_at' => now($timezone)->locale('id')->translatedFormat('j F Y H:i').' '.now($timezone)->format('T'),
        ];
    }

    private function filename(string $type, ReportFilters $filters, string $extension): string
    {
        $stamp = in_array($type, ['tunggakan', 'okupansi'], true) ? $filters->asOf->format('Y-m-d') : $filters->period->key();

        return Str::slug('laporan-'.$type.'-'.$stamp).'.'.$extension;
    }
}
