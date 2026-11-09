<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Koreksi tagihan terbit. Positif menambah, negatif mengurangi. Tidak pernah diubah atau dihapus. */
class InvoiceAdjustment extends Model implements TenantExportable
{
    /** @use HasFactory<InvoiceAdjustmentFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = ['invoice_id', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public static function exportName(): string
    {
        return 'penyesuaian_tagihan';
    }

    public static function exportColumns(): array
    {
        return ['id', 'invoice_id', 'amount', 'reason', 'created_by', 'created_at'];
    }
}
