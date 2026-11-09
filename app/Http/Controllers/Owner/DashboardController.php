<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Angka berasal dari DashboardReport, yang memakai kelas query yang sama dengan halaman laporan.
     * Bagian keuangan (pemasukan, tunggakan) hanya dihitung dan dikirim bagi pengguna dengan izin finance.view.
     */
    public function __invoke(Request $request, DashboardReport $dashboard): Response
    {
        $canFinance = $request->user()->can('view-finance');

        return Inertia::render('Owner/Dashboard', [
            'summary' => $dashboard->build($canFinance),
            'can' => ['finance' => $canFinance],
        ]);
    }
}
