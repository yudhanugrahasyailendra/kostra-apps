<?php

namespace App\Models;

use App\Enums\InvoiceItemType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'meta',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceItemType::class,
            // Jumlah (bukan uang), disimpan dengan 2 desimal.
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'meta' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
