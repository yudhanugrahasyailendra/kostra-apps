<?php

namespace App\View\Composers;

use App\Enums\InvoiceStatus;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\Room;
use App\Services\Messaging\MessageQuota;
use App\Tenancy\TenantContext;
use Illuminate\View\View;

/**
 * Data kerangka panel pemilik: kartu properti dan paket di sidebar, penghitung di menu, dan kuota di bilah atas.
 * Semua query lewat model bertenant, jadi hanya menghitung data tenant yang sedang aktif.
 */
class OwnerLayoutComposer
{
    public function __construct(private readonly TenantContext $context, private readonly MessageQuota $quota) {}

    public function compose(View $view): void
    {
        $tenant = $this->context->current();

        if ($tenant === null) {
            // Super Admin tanpa tenant terpilih: kerangka tanpa kartu tenant.
            $view->with('shell', null);

            return;
        }

        $tenant->loadMissing('plan');
        $limit = $this->quota->limit($tenant);
        $used = $this->quota->used($tenant);

        $view->with('shell', [
            'tenantName' => $tenant->name,
            'planName' => $tenant->plan->name,
            'rooms' => Room::query()->count(),
            'roomLimit' => $tenant->plan->max_rooms,
            'quotaLimit' => $limit,
            'quotaUsed' => $used,
            'quotaLeft' => max(0, $limit - $used),
            'pendingProofs' => PaymentProof::query()->pending()->count(),
            'overdueInvoices' => Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count(),
            'openComplaints' => Complaint::query()->open()->count(),
        ]);
    }
}
