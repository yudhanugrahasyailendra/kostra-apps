<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Tiket komplain penghuni. Tidak dihapus: ditutup lewat status done atau rejected. */
class Complaint extends Model implements TenantExportable
{
    /** @use HasFactory<ComplaintFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /** Tiket terbuka lebih lama dari ini ditandai di daftar (KMP-05). */
    public const STALE_AFTER_DAYS = 3;

    protected $fillable = [
        'property_id', 'room_id', 'resident_id', 'category', 'title', 'description', 'status',
        'assigned_to', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** Tiket yang belum ditutup dan dibuat lebih dari $days hari lalu. */
    public function scopeOpenLongerThan(Builder $query, int $days = self::STALE_AFTER_DAYS): void
    {
        $query->whereIn('status', array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::open()))
            ->where('created_at', '<', now()->subDays($days));
    }

    public function isStale(int $days = self::STALE_AFTER_DAYS): bool
    {
        return ! $this->status->isClosed() && $this->created_at->lt(now()->subDays($days));
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /** @return HasMany<ComplaintComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public static function exportName(): string
    {
        return 'komplain';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'property_id', 'room_id', 'resident_id', 'category', 'title', 'description', 'status',
            'assigned_to', 'resolved_at', 'closed_at', 'created_at',
        ];
    }
}
