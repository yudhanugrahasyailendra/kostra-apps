<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemilik kos (akar tenant). Tidak memakai BelongsToTenant: tenant dibaca lewat
 * pengguna yang login atau token, tidak pernah lewat id dari input pengguna.
 */
class Tenant extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan_id',
        'status',
        'phone',
        'timezone',
        'logo_path',
        'trial_ends_at',
        'suspended_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::Suspended;
    }

    public function isClosed(): bool
    {
        return $this->status === TenantStatus::Closed;
    }
}
