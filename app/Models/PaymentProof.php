<?php

namespace App\Models;

use App\Enums\ProofStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Bukti transfer yang menunggu verifikasi. Berkasnya ada di attachments (kategori transfer_proof). */
class PaymentProof extends Model implements TenantExportable
{
    /** @use HasFactory<PaymentProofFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /** Bukti yang belum ditindak lebih lama dari ini memunculkan pengingat ke pemilik. */
    public const REMINDER_AFTER_DAYS = 3;

    protected $fillable = [
        'invoice_id', 'resident_id', 'amount', 'paid_on', 'sender_note', 'status',
        'reviewed_by', 'reviewed_at', 'reject_reason', 'payment_id',
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @param  Builder<PaymentProof>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ProofStatus::Pending->value);
    }

    /**
     * Bukti pending yang sudah lewat batas pengingat (3 hari). Dipakai pengingat ke pemilik.
     *
     * @param  Builder<PaymentProof>  $query
     */
    public function scopeNeedingReminder(Builder $query): void
    {
        $query->pending()->where('created_at', '<=', now()->subDays(self::REMINDER_AFTER_DAYS));
    }

    public static function exportName(): string
    {
        return 'bukti_transfer';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'invoice_id', 'resident_id', 'amount', 'paid_on', 'sender_note', 'status', 'reviewed_by',
            'reviewed_at', 'reject_reason', 'payment_id', 'created_at',
        ];
    }
}
