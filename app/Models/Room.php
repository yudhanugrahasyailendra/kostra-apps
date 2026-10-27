<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'room_type_id',
        'number',
        'floor',
        'price',
        'status',
        'facilities',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'price' => 'integer',
            'status' => RoomStatus::class,
            'facilities' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Fasilitas kamar menimpa fasilitas tipe bila diisi.
     *
     * @return list<string>
     */
    public function effectiveFacilities(): array
    {
        return $this->facilities ?? $this->roomType?->facilities ?? [];
    }

    public function isOccupied(): bool
    {
        return $this->status === RoomStatus::Occupied;
    }
}
