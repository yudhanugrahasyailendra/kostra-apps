<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentTenant;
use App\Services\Plans\PlanLimitExceededException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['web', 'auth', 'tenant', 'tenant.active'])->prefix('app')->name('owner.')
                ->group(base_path('routes/owner.php'));
            Route::middleware('web')->prefix('portal')->name('portal.')
                ->group(base_path('routes/portal.php'));
            Route::middleware(['web', 'auth', 'super_admin'])->prefix('admin')->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);

        // Tenant harus sudah ditetapkan sebelum route model binding, supaya binding terscope tenant.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentTenant::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('owner.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Melewati batas paket bukan galat server: tampilkan sebagai galat formulir dengan tawaran naik paket.
        $exceptions->render(function (PlanLimitExceededException $e) {
            return back()->withErrors(['limit' => $e->getMessage()]);
        });
    })->create();
