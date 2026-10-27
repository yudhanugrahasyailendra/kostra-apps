<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Contracts\InvoicePaidTotal;
use App\Contracts\OutstandingBalance;
use App\Contracts\PortalLink;
use App\Contracts\WhatsAppProvider;
use App\Enums\Permission;
use App\Events\ComplaintStatusChanged;
use App\Events\InvoiceIssued;
use App\Events\PaymentRecorded;
use App\Models\Attachment;
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
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAllocation;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Billing\AllocationInvoicePaidTotal;
use App\Services\Billing\InvoiceOutstandingBalance;
use App\Services\Messaging\AppUrlPortalLink;
use App\Services\Messaging\FakeWhatsAppProvider;
use App\Services\Messaging\Listeners\QueueComplaintStatusMessage;
use App\Services\Messaging\Listeners\QueueInvoiceIssuedMessage;
use App\Services\Messaging\Listeners\QueueReceiptMessage;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(OutstandingBalance::class, InvoiceOutstandingBalance::class);

        $this->app->bind(InvoicePaidTotal::class, AllocationInvoicePaidTotal::class);

        // Penyedia dipilih lewat config/kostra.php. Pengembangan dan tes wajib memakai "fake" (tidak pernah mengirim pesan nyata).
        $this->app->singleton(WhatsAppProvider::class, fn () => match ($name = config('kostra.whatsapp.provider')) {
            'fake' => new FakeWhatsAppProvider,
            default => throw new LogicException("Penyedia WhatsApp \"{$name}\" belum dipasang; gunakan \"fake\"."),
        });

        // Diganti tautan portal bertoken di Fase 10.
        $this->app->bind(PortalLink::class, AppUrlPortalLink::class);
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
            'payment' => Payment::class,
            'payment_allocation' => PaymentAllocation::class,
            'payment_proof' => PaymentProof::class,
            'payment_account' => PaymentAccount::class,
            'expense' => Expense::class,
            'expense_category' => ExpenseCategory::class,
            'complaint' => Complaint::class,
            'complaint_comment' => ComplaintComment::class,
            'message_template' => MessageTemplate::class,
            'message_log' => MessageLog::class,
            'usage_counter' => UsageCounter::class,
        ]);

        Auth::provider('tenant-eloquent', fn ($app, array $config) => new TenantAwareUserProvider($app['hash'], $config['model']));

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Gate::define('subscription.manage', fn (User $user) => $user->isOwner());

        // Pemicu pesan WhatsApp. Pendengar berada di luar app/Listeners supaya tidak terdaftar ganda oleh penemuan otomatis.
        Event::listen(InvoiceIssued::class, [QueueInvoiceIssuedMessage::class, 'handle']);
        Event::listen(PaymentRecorded::class, [QueueReceiptMessage::class, 'handle']);
        Event::listen(ComplaintStatusChanged::class, [QueueComplaintStatusMessage::class, 'handle']);
    }
}
