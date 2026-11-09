<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant adalah akar isolasi data, jadi tidak memakai BelongsToTenant.
 */
class Tenant extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'slug', 'plan_id', 'status', 'phone', 'timezone', 'logo_path', 'settings'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::Suspended;
    }

    /** Tenant ditangguhkan atau ditutup hanya boleh membaca dan mengekspor (LGN-07). */
    public function isReadOnly(): bool
    {
        return in_array($this->status, [TenantStatus::Suspended, TenantStatus::Closed], true);
    }

    /** Hanya tenant berstatus trial atau aktif yang boleh menambah pengguna baru. */
    public function canAddUsers(): bool
    {
        return in_array($this->status, [TenantStatus::Trial, TenantStatus::Active], true);
    }
}
