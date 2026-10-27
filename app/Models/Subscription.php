<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_period',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'grace_ends_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'grace_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class)->orderBy('period_start')->orderBy('id');
    }

    public function scopeCurrent(Builder $query): void
    {
        $query->whereIn('status', SubscriptionStatus::currentValues());
    }

    public function isFree(): bool
    {
        return $this->plan->price_monthly === 0;
    }
}
