<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeaseResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseResident extends Model implements TenantExportable
{
    /** @use HasFactory<LeaseResidentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['lease_id', 'resident_id', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public static function exportName(): string
    {
        return 'penghuni_kontrak';
    }

    public static function exportColumns(): array
    {
        return ['id', 'lease_id', 'resident_id', 'is_primary', 'created_at'];
    }
}
