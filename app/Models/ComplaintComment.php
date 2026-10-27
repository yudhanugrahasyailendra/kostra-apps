<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintComment extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Isi komentar bisa memuat data pribadi. */
    protected array $auditMasked = ['body'];

    protected $fillable = [
        'tenant_id',
        'complaint_id',
        'user_id',
        'resident_id',
        'body',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function authorName(): string
    {
        return $this->user?->name ?? $this->resident?->name ?? 'Tidak diketahui';
    }

    public function isByResident(): bool
    {
        return $this->resident_id !== null;
    }
}
