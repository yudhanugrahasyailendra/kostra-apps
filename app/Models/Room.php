<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model implements TenantExportable
{
    /** @use HasFactory<RoomFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['property_id', 'room_type_id', 'number', 'floor', 'price', 'status', 'facilities', 'notes'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'floor' => 'integer',
            'status' => RoomStatus::class,
            'facilities' => 'array',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<RoomType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public static function exportName(): string
    {
        return 'kamar';
    }

    public static function exportColumns(): array
    {
        return ['id', 'property_id', 'room_type_id', 'number', 'floor', 'price', 'status', 'facilities', 'notes', 'created_at'];
    }
}
