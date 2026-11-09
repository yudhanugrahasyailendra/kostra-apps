<?php

namespace App\Models;

use App\Enums\InvoiceItemType;
use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model implements TenantExportable
{
    /** @use HasFactory<InvoiceItemFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['invoice_id', 'type', 'description', 'quantity', 'unit_price', 'amount', 'meta', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceItemType::class,
            // quantity bukan uang; pecahan dipakai untuk meteran pada tahap berikutnya.
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'meta' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function exportName(): string
    {
        return 'item_tagihan';
    }

    public static function exportColumns(): array
    {
        return ['id', 'invoice_id', 'type', 'description', 'quantity', 'unit_price', 'amount', 'sort_order', 'created_at'];
    }
}
