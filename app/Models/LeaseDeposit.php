<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeaseDepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deposit tercatat sebagai kewajiban yang dapat dikembalikan. Kostra hanya mencatat; uangnya
 * diserahkan dan dikembalikan di luar sistem.
 */
class LeaseDeposit extends Model implements TenantExportable
{
    /** @use HasFactory<LeaseDepositFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'lease_id', 'amount', 'received_at', 'status',
        'deduction_amount', 'deduction_note', 'refund_amount', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'amount' => 'integer',
            'deduction_amount' => 'integer',
            'refund_amount' => 'integer',
            'received_at' => 'date',
            'refunded_at' => 'date',
        ];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public static function exportName(): string
    {
        return 'deposit';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'lease_id', 'amount', 'received_at', 'status', 'deduction_amount', 'deduction_note',
            'refund_amount', 'refunded_at', 'created_at',
        ];
    }
}
