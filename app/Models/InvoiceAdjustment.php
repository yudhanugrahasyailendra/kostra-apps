<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAdjustment extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'invoice_id', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
