<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Enums\Permission;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Nama kelas tidak boleh tersimpan di database (audit_logs, attachments, dst.).
        Relation::enforceMorphMap([
            'tenant' => Tenant::class,
            'user' => User::class,
            'invitation' => Invitation::class,
        ]);

        Auth::provider('tenant-eloquent', fn ($app, array $config) => new TenantAwareUserProvider($app['hash'], $config['model']));

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Gate::define('subscription.manage', fn (User $user) => $user->isOwner());
    }
}
