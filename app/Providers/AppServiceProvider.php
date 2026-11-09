<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Exports\TenantExportRegistry;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: konteks tenant direset untuk tiap request/job (aman untuk Octane dan queue worker).
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(TenantExportRegistry::class);
    }

    public function boot(): void
    {
        Auth::provider('tenant-eloquent', function (Application $app, array $config) {
            return new TenantUserProvider($app['hash'], $config['model']);
        });

        // Kolom *_type polimorfik (attachments, audit_logs) menyimpan alias ini, bukan nama kelas.
        // Model baru yang dipakai relasi polimorfik wajib didaftarkan; yang lupa akan melempar galat.
        Relation::enforceMorphMap([
            'tenant' => Tenant::class,
            'plan' => Plan::class,
            'user' => User::class,
            'invitation' => Invitation::class,
            'audit_log' => AuditLog::class,
            'property' => Property::class,
            'room_type' => RoomType::class,
            'room' => Room::class,
            'resident' => Resident::class,
            'attachment' => Attachment::class,
        ]);

        // Daftar model yang boleh diekspor (NFR-12). Fase berikutnya menambah modelnya di sini.
        $registry = $this->app->make(TenantExportRegistry::class);
        $registry->register(User::class);
        $registry->register(Invitation::class);
        $registry->register(Property::class);
        $registry->register(RoomType::class);
        $registry->register(Room::class);
        $registry->register(Resident::class);
        $registry->register(Attachment::class);

        Gate::define('export-data', fn (User $user) => $user->hasPermission(Permission::DataExport));
        Gate::define('view-finance', fn (User $user) => $user->hasPermission(Permission::FinanceView));
        Gate::define('manage-settings', fn (User $user) => $user->hasPermission(Permission::SettingsManage));
    }
}
