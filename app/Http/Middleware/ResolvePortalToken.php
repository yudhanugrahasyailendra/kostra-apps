<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu satu-satunya portal publik. Token dari URL diperiksa (ada, belum dicabut, belum kedaluwarsa, penghuninya
 * masih ada, tenantnya tidak ditutup) lalu penghuni dan tenantnya ditaruh di request. Satu token = satu penghuni
 * = satu tenant; semua data portal diambil lewat penghuni itu, tidak pernah lewat id mentah dari URL.
 *
 * SEMUA kegagalan menghasilkan respons yang persis sama, supaya token tidak bisa ditebak dari alasannya.
 * Percobaan gagal berulang dari satu alamat dibatasi lajunya.
 */
class ResolvePortalToken
{
    /** Kegagalan per menit per alamat sebelum diblokir sementara. */
    public const MAX_FAILURES = 10;

    /** Pembaruan last_used_at paling sering sekali per menit, supaya tidak menulis di setiap permintaan. */
    private const TOUCH_AFTER_SECONDS = 60;

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $failureKey = 'portal-token-failures:'.$request->ip();

        if (RateLimiter::tooManyAttempts($failureKey, self::MAX_FAILURES)) {
            return $this->protect(response()->view('portal.invalid', [], 429));
        }

        $resolved = $this->resolve((string) $request->route('token'));

        if ($resolved === null) {
            RateLimiter::hit($failureKey, 60);

            return $this->protect(response()->view('portal.invalid', [], 404));
        }

        [$token, $tenant, $resident] = $resolved;

        // Tenant ditangguhkan hanya boleh membaca: penghuni masih bisa melihat tagihan, tetapi tidak mengubah apa pun.
        if ($tenant->isSuspended() && ! $request->isMethodSafe()) {
            return $this->protect(response()->view('portal.invalid', [], 404));
        }

        $this->touch($token);

        $request->attributes->set('portal_token', $token);
        $request->attributes->set('portal_resident', $resident);
        $request->attributes->set('portal_tenant', $tenant);

        $this->context->set($tenant);

        // Tampilan membangun tautan dari token di URL; penghuni dan tenant dipakai untuk kepala halaman.
        View::share(['portalToken' => (string) $request->route('token'), 'portalResident' => $resident, 'portalTenant' => $tenant]);

        try {
            return $this->protect($next($request));
        } finally {
            // Konteks tenant tidak boleh bocor ke permintaan lain di proses yang sama.
            $this->context->forget();
        }
    }

    /**
     * @return array{0: PortalToken, 1: Tenant, 2: Resident}|null
     */
    private function resolve(string $raw): ?array
    {
        if (! PortalToken::looksValid($raw)) {
            return null;
        }

        // Pencarian token adalah pengecualian tanpa tenant yang disengaja: tenant baru diketahui dari token itu sendiri.
        $token = $this->context->withoutTenant(
            fn () => PortalToken::query()->where('token_hash', PortalToken::hash($raw))->first(),
        );

        if ($token === null || ! $token->isUsable()) {
            return null;
        }

        $tenant = Tenant::query()->find($token->tenant_id);

        if ($tenant === null || ! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial, TenantStatus::Suspended], true)) {
            return null;
        }

        // Penghuni dicari dalam konteks tenant token itu; penghuni yang dihapus (atau milik tenant lain) tidak ditemukan.
        $resident = $this->context->run($tenant, fn () => Resident::query()->find($token->resident_id));

        return $resident === null ? null : [$token, $tenant, $resident];
    }

    private function touch(PortalToken $token): void
    {
        if ($token->last_used_at !== null && $token->last_used_at->gt(now()->subSeconds(self::TOUCH_AFTER_SECONDS))) {
            return;
        }

        // Pembaruan satu baris menurut id token yang sudah divalidasi; tanpa tenant karena konteks belum ditetapkan.
        $this->context->run($token->tenant_id, fn () => PortalToken::query()->whereKey($token->getKey())->update(['last_used_at' => now()]));
        $token->last_used_at = now();
    }

    /**
     * Tautan berisi rahasia: jangan di-cache, jangan ikut terkirim sebagai Referer, dan jangan diindeks.
     */
    private function protect(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
