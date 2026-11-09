<?php

namespace App\Models;

use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Langganan Kostra milik satu tenant. Satu tenant punya satu langganan berjalan; yang lama tetap ada sebagai riwayat. */
class Subscription extends Model implements TenantExportable
{
    /** @use HasFactory<SubscriptionFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'plan_id', 'status', 'billing_period', 'trial_ends_at', 'current_period_start',
        'current_period_end', 'grace_ends_at', 'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => SubscriptionPeriod::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'grace_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SubscriptionInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    public static function exportName(): string
    {
        return 'langganan';
    }

    public static function exportColumns(): array
    {
        return ['id', 'plan_id', 'status', 'billing_period', 'trial_ends_at', 'current_period_start', 'current_period_end', 'grace_ends_at', 'canceled_at', 'created_at'];
    }
}
