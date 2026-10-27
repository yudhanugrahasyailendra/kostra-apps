<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Services\Export\TenantDataExporter;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor seluruh data tenant. Hanya pemilik. Sengaja tidak memeriksa paket atau status langganan: pemilik selalu
 * bisa membawa datanya, termasuk saat ditangguhkan atau langganannya berakhir (PRD, aturan bisnis langganan).
 */
class ExportController extends Controller
{
    public function index(TenantDataExporter $exporter): View
    {
        Gate::authorize('export-data');

        return view('owner.export', ['counts' => $exporter->counts()]);
    }

    public function download(TenantDataExporter $exporter, TenantContext $context, RecordAuditLog $audit): BinaryFileResponse
    {
        Gate::authorize('export-data');

        $tenant = $context->current();

        // Berkas ini memuat data pribadi penghuni, jadi setiap unduhan tercatat.
        $audit(AuditEvent::Exported, $tenant, null, ['sheets' => array_keys($exporter->sheets())]);

        return $exporter->xlsx('data-'.$tenant->slug.'-'.$tenant->today()->format('Ymd').'.xlsx');
    }
}
