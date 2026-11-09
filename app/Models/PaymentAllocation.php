<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bagian sebuah pembayaran yang menutup satu tagihan. Tidak pernah diubah atau dihapus; pembatalan lewat payments.status. */
class PaymentAllocation extends Model implements TenantExportable
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = ['payment_id', 'invoice_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function exportName(): string
    {
        return 'alokasi_pembayaran';
    }

    public static function exportColumns(): array
    {
        return ['id', 'payment_id', 'invoice_id', 'amount', 'created_at'];
    }
}
