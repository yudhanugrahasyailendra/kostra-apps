<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseDeposit extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'amount',
        'received_at',
        'status',
        'deduction_amount',
        'deduction_note',
        'refund_amount',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'received_at' => 'date',
            'status' => DepositStatus::class,
            'deduction_amount' => 'integer',
            'refund_amount' => 'integer',
            'refunded_at' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
