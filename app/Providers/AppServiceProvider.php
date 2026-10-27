<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Contracts\InvoicePaidTotal;
use App\Contracts\OutstandingBalance;
use App\Enums\Permission;
use App\Models\Attachment;
use App\Models\BillingSetting;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceOutstandingBalance;
use App\Services\Billing\NullInvoicePaidTotal;
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

        $this->app->bind(OutstandingBalance::class, InvoiceOutstandingBalance::class);

        // Diganti perhitungan dari payment_allocations di Fase 6.
        $this->app->bind(InvoicePaidTotal::class, NullInvoicePaidTotal::class);
    }

    public function boot(): void
    {
        // Nama kelas tidak boleh tersimpan di database (audit_logs, attachments, dst.).
        Relation::enforceMorphMap([
            'tenant' => Tenant::class,
            'user' => User::class,
            'invitation' => Invitation::class,
            'property' => Property::class,
            'room_type' => RoomType::class,
            'room' => Room::class,
            'resident' => Resident::class,
            'attachment' => Attachment::class,
            'lease' => Lease::class,
            'lease_resident' => LeaseResident::class,
            'lease_deposit' => LeaseDeposit::class,
            'room_transfer' => RoomTransfer::class,
            'invoice' => Invoice::class,
            'invoice_item' => InvoiceItem::class,
            'invoice_adjustment' => InvoiceAdjustment::class,
            'billing_setting' => BillingSetting::class,
        ]);

        Auth::provider('tenant-eloquent', fn ($app, array $config) => new TenantAwareUserProvider($app['hash'], $config['model']));

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Gate::define('subscription.manage', fn (User $user) => $user->isOwner());
    }
}
