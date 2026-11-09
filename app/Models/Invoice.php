<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan. Angka (subtotal, adjustment_total, total, paid_total, status) hanya boleh diubah
 * oleh RecalculateInvoice, VoidInvoice, dan Action penerbitan; jumlah tagihan terbit tidak
 * pernah diedit langsung, koreksi lewat invoice_adjustments. Tagihan tidak pernah dihapus.
 */
class Invoice extends Model implements TenantExportable
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'property_id', 'lease_id', 'resident_id', 'number', 'type', 'period', 'period_start', 'period_end',
        'issue_date', 'due_date', 'status', 'subtotal', 'adjustment_total', 'total', 'paid_total',
        'issued_at', 'paid_at', 'voided_at', 'void_reason', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
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

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<InvoiceAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class)->orderBy('id');
    }

    /** Sisa tagihan: dihitung, tidak disimpan. Tagihan batal tidak punya sisa. */
    public function outstanding(): int
    {
        return $this->status === InvoiceStatus::Void ? 0 : max(0, $this->total - $this->paid_total);
    }

    /** @param  Builder<Invoice>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::openValues());
    }

    public static function exportName(): string
    {
        return 'tagihan';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'property_id', 'lease_id', 'resident_id', 'number', 'type', 'period', 'period_start', 'period_end',
            'issue_date', 'due_date', 'status', 'subtotal', 'adjustment_total', 'total', 'paid_total',
            'issued_at', 'paid_at', 'voided_at', 'void_reason', 'notes', 'created_at',
        ];
    }
}
