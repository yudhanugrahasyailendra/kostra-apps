<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model implements TenantExportable
{
    /** @use HasFactory<RoomTypeFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = ['property_id', 'name', 'base_price', 'size_m2', 'facilities', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'size_m2' => 'decimal:1',
            'facilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public static function exportName(): string
    {
        return 'tipe_kamar';
    }

    public static function exportColumns(): array
    {
        return ['id', 'property_id', 'name', 'base_price', 'size_m2', 'facilities', 'description', 'is_active', 'created_at'];
    }
}
