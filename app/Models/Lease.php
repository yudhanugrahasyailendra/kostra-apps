<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lease extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Batas "segera berakhir" yang ditampilkan di daftar dan penanda. */
    public const EXPIRING_SOON_DAYS = 30;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'room_id',
        'primary_resident_id',
        'status',
        'active_room_id',
        'start_date',
        'end_date',
        'billing_cycle',
        'rent_amount',
        'due_day',
        'renewed_from_lease_id',
        'checked_in_at',
        'checkin_notes',
        'checked_out_at',
        'checkout_notes',
        'ended_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeaseStatus::class,
            'billing_cycle' => BillingCycle::class,
            'ended_reason' => LeaseEndReason::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'integer',
            'due_day' => 'integer',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function primaryResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'primary_resident_id');
    }

    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'lease_residents')->withPivot('is_primary');
    }

    public function leaseResidents(): HasMany
    {
        return $this->hasMany(LeaseResident::class);
    }

    public function deposit(): HasOne
    {
        return $this->hasOne(LeaseDeposit::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(RoomTransfer::class)->orderBy('transferred_on')->orderBy('id');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_lease_id');
    }

    public function renewal(): HasOne
    {
        return $this->hasOne(self::class, 'renewed_from_lease_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', LeaseStatus::Active->value);
    }

    /**
     * Kontrak aktif yang berakhir dalam $days hari ke depan (termasuk hari ini dan hari ke-$days),
     * menurut tanggal di zona waktu tenant. Kontrak yang sudah lewat tidak termasuk (lihat isPastEnd).
     */
    public function scopeExpiringWithin(Builder $query, int $days, ?CarbonInterface $today = null): void
    {
        $today ??= self::tenantToday();

        $query->where('status', LeaseStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays($days)->toDateString()]);
    }

    /**
     * "Lewat masa sewa": kontrak aktif yang tanggal akhirnya sudah lewat tetapi belum check-out.
     * Nilai terhitung, bukan kolom.
     */
    public function isPastEnd(?CarbonInterface $today = null): bool
    {
        $today ??= self::tenantToday();

        return $this->status === LeaseStatus::Active
            && $this->end_date !== null
            && $this->end_date->toDateString() < $today->toDateString();
    }

    /**
     * Sisa hari sampai berakhir (negatif bila sudah lewat); null bila tanpa tanggal akhir.
     */
    public function daysRemaining(?CarbonInterface $today = null): ?int
    {
        if ($this->end_date === null) {
            return null;
        }

        $today ??= self::tenantToday();

        // Bandingkan tanggal kalender saja: jam dan zona waktu tidak boleh menggeser hitungan hari.
        $from = CarbonImmutable::parse($today->toDateString(), 'UTC');
        $to = CarbonImmutable::parse($this->end_date->toDateString(), 'UTC');

        return (int) round($from->diffInDays($to, false));
    }

    /**
     * Tanggal hari ini menurut zona waktu tenant aktif.
     */
    public static function tenantToday(): CarbonImmutable
    {
        return app(TenantContext::class)->current()?->today() ?? now()->toImmutable()->startOfDay();
    }
}
