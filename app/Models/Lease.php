<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\LeaseEndReason;
use App\Enums\LeaseStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Kontrak sewa. `active_room_id` diisi `room_id` selama kontrak aktif dan null sesudahnya;
 * unique di database menjamin satu kamar tidak pernah punya dua kontrak aktif.
 * Kolom itu hanya boleh diubah oleh Action kontrak (CheckInResident, CheckOutResident, RenewLease, TransferRoom).
 */
class Lease extends Model implements TenantExportable
{
    /** @use HasFactory<LeaseFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /** Interval pengingat kontrak segera habis (KTR-06), tetap pada MVP. */
    public const EXPIRY_REMINDER_DAYS = [30, 14, 7];

    protected $fillable = [
        'property_id', 'room_id', 'primary_resident_id', 'status', 'active_room_id',
        'start_date', 'end_date', 'billing_cycle', 'rent_amount', 'due_day', 'renewed_from_lease_id',
        'checked_in_at', 'checkin_notes', 'checked_out_at', 'checkout_notes', 'ended_reason', 'notes',
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

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /** @return BelongsTo<Resident, $this> */
    public function primaryResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'primary_resident_id')->withTrashed();
    }

    /** @return HasMany<LeaseResident, $this> */
    public function leaseResidents(): HasMany
    {
        return $this->hasMany(LeaseResident::class);
    }

    /** @return HasOne<LeaseDeposit, $this> */
    public function deposit(): HasOne
    {
        return $this->hasOne(LeaseDeposit::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<RoomTransfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(RoomTransfer::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_lease_id');
    }

    /** @param  Builder<Lease>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', LeaseStatus::Active->value);
    }

    /**
     * Kontrak aktif yang berakhir dalam $days hari ke depan, termasuk hari ini dan hari ke-$days.
     * Kontrak tanpa tanggal akhir tidak pernah masuk. Dipakai pengingat 30, 14, dan 7 hari.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $today = Carbon::today();

        $query->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()]);
    }

    /**
     * Kontrak aktif yang tanggal akhirnya sudah lewat tetapi belum check-out ("lewat masa sewa").
     * Nilai terhitung, bukan kolom.
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->active()->whereNotNull('end_date')->where('end_date', '<', Carbon::today()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status === LeaseStatus::Active
            && $this->end_date !== null
            && $this->end_date->lt(Carbon::today());
    }

    /** Sisa hari sampai tanggal akhir (negatif bila lewat); null untuk sewa tanpa tanggal akhir. */
    public function daysRemaining(): ?int
    {
        return $this->end_date === null ? null : (int) Carbon::today()->diffInDays($this->end_date, false);
    }

    public static function exportName(): string
    {
        return 'kontrak';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'property_id', 'room_id', 'primary_resident_id', 'status', 'start_date', 'end_date', 'billing_cycle',
            'rent_amount', 'due_day', 'renewed_from_lease_id', 'checked_in_at', 'checkin_notes', 'checked_out_at',
            'checkout_notes', 'ended_reason', 'notes', 'created_at',
        ];
    }
}
