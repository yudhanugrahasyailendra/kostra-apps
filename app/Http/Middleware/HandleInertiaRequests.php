<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props bersama. Hanya data yang dipakai kerangka layar; izin dikirim dari
     * Gate (`can`) hanya untuk menyembunyikan menu, otorisasi tetap di server.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => fn () => $user ? [
                'user' => (new UserResource($user))->resolve(),
                'can' => [
                    'manageStaff' => $user->can('viewAny', User::class),
                    'viewAudit' => $user->can('viewAny', AuditLog::class),
                    'viewFinance' => $user->can('view-finance'),
                    'manageSettings' => $user->can('manage-settings'),
                    'exportData' => $user->can('export-data'),
                    'manageSubscription' => $user->can('viewAny', Subscription::class),
                ],
            ] : null,
            // Dievaluasi saat render, setelah SetCurrentTenant menetapkan tenant.
            'tenant' => function () {
                $tenant = app(TenantContext::class)->tenant();

                return $tenant ? [
                    'name' => $tenant->name,
                    'status' => $tenant->status->value,
                    'suspended' => $tenant->isSuspended(),
                ] : null;
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'invitation_link' => fn () => $request->session()->get('invitation_link'),
                'import_result' => fn () => $request->session()->get('import_result'),
                'resident_import_result' => fn () => $request->session()->get('resident_import_result'),
            ],
        ];
    }
}
