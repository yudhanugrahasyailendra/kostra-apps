<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            // Super Admin tidak punya tenant; panel Admin memang lintas tenant (hanya lewat role:super_admin).
            $this->context->enterUnscoped();

            return $next($request);
        }

        $tenant = $user->tenant_id === null ? null : $user->tenant()->first();

        if ($tenant === null || $tenant->isClosed()) {
            Auth::logout();
            $request->session()->invalidate();

            abort(403, 'Akun tidak dapat digunakan.');
        }

        $this->context->set($tenant);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }
}
