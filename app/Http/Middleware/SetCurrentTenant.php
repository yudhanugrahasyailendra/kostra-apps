<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan tenant aktif dari pengguna yang login. Pengguna tanpa tenant
 * (Super Admin) atau yang dinonaktifkan tidak boleh memakai panel pemilik.
 */
class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Akun Anda dinonaktifkan.']);
        }

        $tenant = $user->tenant_id ? Tenant::query()->find($user->tenant_id) : null;

        abort_if($tenant === null, 403, 'Akun ini tidak terhubung ke tenant.');

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
