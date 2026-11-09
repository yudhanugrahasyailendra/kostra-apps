<?php

namespace App\Http\Middleware;

use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu masuk portal penghuni. Memeriksa token di URL (ada, belum dicabut, belum kedaluwarsa), menetapkan
 * tenant pemiliknya, lalu menaruh penghuni di `portal_resident`. Tanpa sesi dan tanpa login: tiap request
 * membawa tokennya sendiri, jadi portal tidak pernah menjadi pengguna terautentikasi dan tidak bisa menyentuh
 * rute pemilik atau admin. Semua kegagalan (format salah, tidak ada, dicabut, kedaluwarsa, penghuni dihapus)
 * menghasilkan respons yang sama persis agar penyebabnya tidak bisa ditebak, dan percobaan gagal berulang
 * dari satu IP dibatasi.
 */
class ResolvePortalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $limiterKey = 'portal-invalid:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('kostra.portal.invalid_attempts_per_minute'))) {
            return $this->reject($request, 429);
        }

        $resolved = $this->resolve($request->route('token'));

        if ($resolved === null) {
            RateLimiter::hit($limiterKey, 60);

            return $this->reject($request, 404);
        }

        [$record, $tenant, $resident] = $resolved;

        if ($tenant->isReadOnly() && ! $request->isMethodSafe()) {
            return $this->reject($request, 403);
        }

        TenantContext::instance()->set($tenant);
        $request->attributes->set('portal_resident', $resident);

        // Cukup sekali per beberapa menit agar membuka halaman tidak menulis ke basis data setiap kali.
        if ($record->last_used_at === null || $record->last_used_at->lt(now()->subMinutes(5))) {
            PortalToken::query()->whereKey($record->id)->update(['last_used_at' => now()]);
        }

        $response = $next($request);

        // Tautan berisi rahasia: jangan di-cache, jangan bocor lewat Referer, jangan diindeks mesin pencari.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /** @return array{0: PortalToken, 1: Tenant, 2: Resident}|null */
    private function resolve(mixed $token): ?array
    {
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        // Alasan unscoped: tenant belum diketahui; satu-satunya kunci pencarian adalah hash token (unique).
        $record = TenantContext::instance()->unscoped(fn () => PortalToken::query()->usable()->where('token_hash', PortalToken::hash($token))->first());
        $tenant = $record === null ? null : Tenant::query()->find($record->tenant_id);

        if ($tenant === null) {
            return null;
        }

        // Penghuni dicari dalam konteks tenant token, jadi penghuni tenant lain tidak mungkin ditemukan.
        $resident = TenantContext::instance()->runAs($tenant, fn () => Resident::query()->find($record->resident_id));

        return $resident === null ? null : [$record, $tenant, $resident];
    }

    private function reject(Request $request, int $status): Response
    {
        return Inertia::render('Portal/Invalid', ['status' => $status])
            ->toResponse($request)
            ->setStatusCode($status);
    }
}
