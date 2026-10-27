<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'lease_id',
        'resident_id',
        'number',
        'type',
        'period',
        'period_start',
        'period_end',
        'issue_date',
        'due_date',
        'status',
        'subtotal',
        'adjustment_total',
        'total',
        'paid_total',
        'issued_at',
        'paid_at',
        'voided_at',
        'void_reason',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'type' => InvoiceType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'adjustment_total' => 'integer',
            'total' => 'integer',
            'paid_total' => 'integer',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class)->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->orderBy('id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->orderByDesc('id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::openValues());
    }

    /**
     * Sisa tagihan (dihitung, tidak disimpan).
     */
    public function remaining(): int
    {
        return $this->status === InvoiceStatus::Void ? 0 : max(0, $this->total - $this->paid_total);
    }
}
