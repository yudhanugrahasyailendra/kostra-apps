<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->prefix('owner')->name('owner.')
                ->group(base_path('routes/owner.php'));
            Route::middleware('web')->prefix('p')->name('portal.')
                ->group(base_path('routes/portal.php'));
            Route::middleware('web')->prefix('admin')->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'tenant' => SetCurrentTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
        ]);

        // Konteks tenant harus siap sebelum route model binding, supaya binding terscope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentTenant::class);
        $middleware->appendToPriorityList(after: SetCurrentTenant::class, append: EnsureTenantIsActive::class);

        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isSuperAdmin() ? '/admin' : '/owner');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
