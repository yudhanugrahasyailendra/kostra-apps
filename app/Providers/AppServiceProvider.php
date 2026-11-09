<?php

namespace App\Providers;

use App\Contracts\OutstandingBalance;
use App\Enums\Permission;
use App\Exports\TenantExportRegistry;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseResident;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAllocation;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\PortalToken;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\RoomType;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Billing\InvoiceOutstandingBalance;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: konteks tenant direset untuk tiap request/job (aman untuk Octane dan queue worker).
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(TenantExportRegistry::class);

        $this->app->bind(OutstandingBalance::class, InvoiceOutstandingBalance::class);

        $this->app->singleton(WhatsAppProvider::class, function (Application $app) {
            $name = config('kostra.whatsapp.provider');
            $class = config("kostra.whatsapp.providers.$name");

            if (! is_string($class) || ! is_subclass_of($class, WhatsAppProvider::class)) {
                throw new InvalidArgumentException("Penyedia WhatsApp '$name' tidak terdaftar di config/kostra.php.");
            }

            return $app->make($class);
        });
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
            'lease' => Lease::class,
            'lease_resident' => LeaseResident::class,
            'lease_deposit' => LeaseDeposit::class,
            'room_transfer' => RoomTransfer::class,
            'billing_setting' => BillingSetting::class,
            'number_sequence' => NumberSequence::class,
            'invoice' => Invoice::class,
            'invoice_item' => InvoiceItem::class,
            'invoice_adjustment' => InvoiceAdjustment::class,
            'payment_account' => PaymentAccount::class,
            'payment' => Payment::class,
            'payment_allocation' => PaymentAllocation::class,
            'payment_proof' => PaymentProof::class,
            'expense_category' => ExpenseCategory::class,
            'expense' => Expense::class,
            'complaint' => Complaint::class,
            'complaint_comment' => ComplaintComment::class,
            'message_template' => MessageTemplate::class,
            'message_log' => MessageLog::class,
            'usage_counter' => UsageCounter::class,
            'portal_token' => PortalToken::class,
            'subscription' => Subscription::class,
            'subscription_invoice' => SubscriptionInvoice::class,
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
        $registry->register(Lease::class);
        $registry->register(LeaseResident::class);
        $registry->register(LeaseDeposit::class);
        $registry->register(RoomTransfer::class);
        $registry->register(BillingSetting::class);
        $registry->register(NumberSequence::class);
        $registry->register(Invoice::class);
        $registry->register(InvoiceItem::class);
        $registry->register(InvoiceAdjustment::class);
        $registry->register(PaymentAccount::class);
        $registry->register(Payment::class);
        $registry->register(PaymentAllocation::class);
        $registry->register(PaymentProof::class);
        $registry->register(ExpenseCategory::class);
        $registry->register(Expense::class);
        $registry->register(Complaint::class);
        $registry->register(ComplaintComment::class);
        $registry->register(MessageTemplate::class);
        $registry->register(MessageLog::class);
        $registry->register(UsageCounter::class);
        $registry->register(PortalToken::class);
        $registry->register(Subscription::class);
        $registry->register(SubscriptionInvoice::class);

        // Listener di app/Listeners (QueueInvoiceIssuedMessage, QueueReceiptMessage, QueueComplaintStatusMessage)
        // ditemukan otomatis lewat tipe parameter handle(); jangan didaftarkan lagi di sini agar tidak berjalan dua kali.
        // Di produksi jalankan `php artisan event:cache` saat deploy.

        // Portal publik: batas per IP untuk semua halaman, dan batas lebih ketat per penghuni untuk
        // unggahan bukti, pengajuan komplain, dan komentar.
        RateLimiter::for('portal', fn (Request $request) => Limit::perMinute((int) config('kostra.portal.requests_per_minute'))->by($request->ip()));
        RateLimiter::for('portal-write', fn (Request $request) => Limit::perMinute((int) config('kostra.portal.writes_per_minute'))
            ->by('portal-write:'.($request->attributes->get('portal_resident')?->id ?? $request->ip())));

        Gate::define('export-data', fn (User $user) => $user->hasPermission(Permission::DataExport));
        Gate::define('view-finance', fn (User $user) => $user->hasPermission(Permission::FinanceView));
        Gate::define('manage-settings', fn (User $user) => $user->hasPermission(Permission::SettingsManage));
    }
}
