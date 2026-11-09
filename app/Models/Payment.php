<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uang yang diterima pemilik di luar sistem. Dibuat hanya oleh RecordPayment dan diubah hanya oleh
 * VoidPayment; tidak pernah dihapus. Kuitansi adalah kolom di baris ini.
 */
class Payment extends Model implements TenantExportable
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'resident_id', 'payment_account_id', 'amount', 'paid_on', 'method', 'reference', 'status',
        'voided_at', 'void_reason', 'voided_by', 'recorded_by', 'receipt_number', 'receipt_issued_at',
        'receipt_sent_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'voided_at' => 'datetime',
            'receipt_issued_at' => 'datetime',
            'receipt_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /** @return BelongsTo<PaymentAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->orderBy('id');
    }

    /** @param  Builder<Payment>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PaymentStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === PaymentStatus::Active;
    }

    public static function exportName(): string
    {
        return 'pembayaran';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'resident_id', 'payment_account_id', 'amount', 'paid_on', 'method', 'reference', 'status',
            'voided_at', 'void_reason', 'recorded_by', 'receipt_number', 'receipt_issued_at', 'receipt_sent_at',
            'notes', 'created_at',
        ];
    }
}
