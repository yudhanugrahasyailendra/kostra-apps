<?php

namespace App\Models;

use App\Enums\SubscriptionInvoiceStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SubscriptionInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tagihan langganan Kostra ke pemilik. Nomornya terpisah dari tagihan penghuni (`invoices`). Jumlah tidak diubah; batal lewat status void. */
class SubscriptionInvoice extends Model implements TenantExportable
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'subscription_id', 'number', 'period_start', 'period_end', 'amount', 'status', 'due_date',
        'paid_at', 'payment_method', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionInvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionInvoiceStatus::Pending;
    }

    public static function exportName(): string
    {
        return 'tagihan_langganan';
    }

    public static function exportColumns(): array
    {
        return ['id', 'subscription_id', 'number', 'period_start', 'period_end', 'amount', 'status', 'due_date', 'paid_at', 'payment_method', 'reference', 'created_at'];
    }
}
