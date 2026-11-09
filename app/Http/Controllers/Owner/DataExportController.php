<?php

namespace App\Http\Controllers\Owner;

use App\Actions\ExportTenantData;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportController extends Controller
{
    public function __invoke(ExportTenantData $export): BinaryFileResponse
    {
        $this->authorize('export-data');

        $path = $export->handle();

        return response()
            ->download($path, 'ekspor-kostra-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
