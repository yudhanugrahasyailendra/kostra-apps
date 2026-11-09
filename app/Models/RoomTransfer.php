<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTransfer extends Model implements TenantExportable
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['lease_id', 'from_room_id', 'to_room_id', 'transferred_on', 'new_rent_amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'transferred_on' => 'date',
            'new_rent_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id')->withTrashed();
    }

    /** @return BelongsTo<Room, $this> */
    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id')->withTrashed();
    }

    public static function exportName(): string
    {
        return 'pindah_kamar';
    }

    public static function exportColumns(): array
    {
        return ['id', 'lease_id', 'from_room_id', 'to_room_id', 'transferred_on', 'new_rent_amount', 'reason', 'created_by', 'created_at'];
    }
}
