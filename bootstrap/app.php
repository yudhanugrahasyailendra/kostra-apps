<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\PortalStartSession;
use App\Http\Middleware\ResolvePortalToken;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->prefix('owner')->name('owner.')
                ->group(base_path('routes/owner.php'));
            Route::middleware('portal')->prefix('p')->name('portal.')
                ->group(base_path('routes/portal.php'));
            Route::middleware('web')->prefix('admin')->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Portal: sama dengan grup web, tetapi sesinya tidak menyimpan URL halaman (URL memuat token asli).
        $middleware->group('portal', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            PortalStartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'tenant' => SetCurrentTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'portal.token' => ResolvePortalToken::class,
        ]);

        // Header keamanan untuk semua respons web dan portal.
        $middleware->append(SecurityHeaders::class);

        // Konteks tenant harus siap sebelum route model binding, supaya binding terscope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentTenant::class);
        $middleware->appendToPriorityList(after: SetCurrentTenant::class, append: EnsureTenantIsActive::class);

        // Peran diperiksa sebelum route model binding: pengguna tanpa peran mendapat 403 yang sama, ada atau tidaknya id.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureRole::class);

        // Portal: token divalidasi dan tenant ditetapkan sebelum binding apa pun.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolvePortalToken::class);

        // IP klien yang benar menentukan pembatas laju (login, portal). Di belakang proksi atau penyeimbang beban,
        // isi TRUSTED_PROXIES dengan alamatnya (pisahkan koma), atau "*" bila hanya penyeimbang beban tepercaya
        // yang bisa menjangkau aplikasi. Kosong = tidak ada proksi tepercaya.
        if (filled($proxies = env('TRUSTED_PROXIES'))) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isSuperAdmin() ? '/admin' : '/owner');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pesan QueryException memuat SQL beserta nilai binding (nama, nomor telepon, isi catatan). Log aplikasi
        // hanya boleh memuat kode galat dan lokasinya, tidak pernah nilai datanya.
        $exceptions->report(function (QueryException $e) {
            Log::error('Galat basis data.', [
                'sqlstate' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ]);
        })->stop();
    })->create();
