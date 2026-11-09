<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UsageCounterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Penghitung pemakaian per tenant, metrik, dan periode (Y-m). Hanya diubah lewat MessageQuota. */
class UsageCounter extends Model implements TenantExportable
{
    /** @use HasFactory<UsageCounterFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = ['metric', 'period', 'used'];

    protected function casts(): array
    {
        return ['used' => 'integer'];
    }

    public static function exportName(): string
    {
        return 'penghitung_pemakaian';
    }

    public static function exportColumns(): array
    {
        return ['id', 'metric', 'period', 'used'];
    }
}
