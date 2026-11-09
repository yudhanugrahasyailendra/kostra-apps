<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model implements TenantExportable
{
    /** @use HasFactory<ResidentFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    /** Data pribadi tidak disalin ke audit_logs; nama dan perubahan status cukup untuk menelusuri. */
    protected array $auditExclude = ['phone', 'email', 'emergency_name', 'emergency_relation', 'emergency_phone', 'notes'];

    protected $fillable = [
        'name', 'phone', 'email', 'occupation',
        'emergency_name', 'emergency_relation', 'emergency_phone',
        'notes', 'whatsapp_opt_out_at', 'consented_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_out_at' => 'datetime',
            'consented_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasMany<LeaseResident, $this> */
    public function leaseResidents(): HasMany
    {
        return $this->hasMany(LeaseResident::class);
    }

    public static function exportName(): string
    {
        return 'penghuni';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'name', 'phone', 'email', 'occupation',
            'emergency_name', 'emergency_relation', 'emergency_phone', 'notes',
            'whatsapp_opt_out_at', 'consented_at', 'is_active', 'created_at',
        ];
    }
}
