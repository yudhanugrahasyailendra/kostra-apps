<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant yang ditangguhkan hanya boleh membaca dan mengekspor (LGN-07):
 * semua metode selain GET/HEAD ditolak, kecuali keluar dari akun.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant?->isSuspended() && ! $request->isMethodSafe() && ! $request->routeIs('logout')) {
            abort(403, 'Akun kos Anda sedang ditangguhkan. Anda masih dapat melihat dan mengekspor data.');
        }

        return $next($request);
    }
}
