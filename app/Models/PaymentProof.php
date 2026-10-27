<?php

namespace App\Models;

use App\Enums\ProofStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PaymentProof extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Bukti yang menunggu lebih lama dari ini memunculkan pengingat ke pemilik (Fase 9). */
    public const STALE_AFTER_DAYS = 3;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'resident_id',
        'amount',
        'paid_on',
        'sender_note',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
            'status' => ProofStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', ProofStatus::Pending->value);
    }

    /**
     * Bukti yang masih menunggu dan sudah lebih tua dari $days hari (tepat $days hari belum dihitung).
     */
    public function scopePendingOlderThan(Builder $query, int $days = self::STALE_AFTER_DAYS, ?CarbonInterface $now = null): void
    {
        $query->pending()->where('created_at', '<', ($now ?? now())->subDays($days));
    }

    public function isStale(): bool
    {
        return $this->status === ProofStatus::Pending
            && $this->created_at->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
