<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Penghitung pemakaian per tenant, metrik, dan periode. Dinaikkan hanya lewat MessageQuota (pembaruan bersyarat).
 */
class UsageCounter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'metric',
        'period',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'used' => 'integer',
        ];
    }
}
