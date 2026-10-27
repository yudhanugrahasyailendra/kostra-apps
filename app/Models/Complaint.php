<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Tiket tidak punya jalur hapus: hanya ditutup (done atau rejected).
 */
class Complaint extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Tiket terbuka lebih lama dari ini ditandai di daftar. */
    public const STALE_AFTER_DAYS = 3;

    /** Isi keluhan bisa memuat data pribadi: perubahannya tercatat di audit, nilainya disamarkan. */
    protected array $auditMasked = ['title', 'description'];

    protected $fillable = [
        'tenant_id',
        'property_id',
        'room_id',
        'resident_id',
        'category',
        'title',
        'description',
        'status',
        'assigned_to',
        'resolved_at',
        'closed_at',
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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class)->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ComplaintStatus::openValues());
    }

    /**
     * Tiket terbuka yang dibuat lebih dari $days hari lalu (tepat $days hari belum dihitung).
     */
    public function scopeOpenLongerThan(Builder $query, int $days = self::STALE_AFTER_DAYS, ?CarbonInterface $now = null): void
    {
        $query->open()->where('created_at', '<', ($now ?? now())->subDays($days));
    }

    public function isOpen(): bool
    {
        return ! $this->status->isClosed();
    }

    public function isStale(): bool
    {
        return $this->isOpen() && $this->created_at->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
