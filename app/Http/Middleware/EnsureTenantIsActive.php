<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant yang ditangguhkan hanya boleh membaca dan mengekspor.
 */
class EnsureTenantIsActive
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->current();

        if ($tenant?->isSuspended()
            && ! $request->isMethodSafe()
            && ! $request->routeIs('owner.export.*')) {
            abort(403, 'Akun ditangguhkan. Anda hanya dapat melihat dan mengekspor data.');
        }

        return $next($request);
    }
}
