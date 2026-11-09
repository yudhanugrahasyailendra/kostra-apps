<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Pembangkit nomor dokumen per tenant, kunci, dan periode. Hanya dibaca lewat GenerateInvoiceNumber (lockForUpdate). */
class NumberSequence extends Model implements TenantExportable
{
    use BelongsToTenant;

    protected $fillable = ['key', 'period', 'last_number'];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }

    public static function exportName(): string
    {
        return 'urutan_nomor';
    }

    public static function exportColumns(): array
    {
        return ['id', 'key', 'period', 'last_number', 'created_at'];
    }
}
