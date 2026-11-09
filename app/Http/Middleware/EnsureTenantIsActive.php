<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant yang ditangguhkan atau ditutup hanya boleh membaca dan mengekspor
 * (LGN-07): semua metode selain GET/HEAD ditolak, kecuali keluar dari akun.
 * Tanpa tenant aktif, request ditolak (gagal tertutup).
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->tenant();

        abort_if($tenant === null, 403, 'Tenant tidak ditetapkan.');

        if ($tenant->isReadOnly() && ! $request->isMethodSafe() && ! $request->routeIs('logout')) {
            abort(403, 'Akun kos Anda sedang ditangguhkan atau ditutup. Anda masih dapat melihat dan mengekspor data.');
        }

        return $next($request);
    }
}
