<?php

namespace App\Models;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'number',
        'period_start',
        'period_end',
        'amount',
        'status',
        'due_date',
        'paid_at',
        'payment_method',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'integer',
            'status' => SubscriptionInvoiceStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionInvoiceStatus::Pending;
    }
}
