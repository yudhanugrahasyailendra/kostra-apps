<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paket langganan. Milik platform, bukan milik tenant.
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'price_monthly',
        'price_yearly',
        'max_properties',
        'max_rooms',
        'max_users',
        'monthly_message_quota',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
