<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ComplaintCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** Komentar pada tiket. Tepat satu dari user_id (pemilik/staf) atau resident_id (penghuni) terisi. */
class ComplaintComment extends Model implements TenantExportable
{
    /** @use HasFactory<ComplaintCommentFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /** Isi komentar adalah percakapan pribadi; tidak disalin ke audit_logs. */
    protected array $auditExclude = ['body'];

    protected $fillable = ['complaint_id', 'user_id', 'resident_id', 'body'];

    protected static function booted(): void
    {
        static::saving(function (self $comment): void {
            if (($comment->user_id === null) === ($comment->resident_id === null)) {
                throw new InvalidArgumentException('Komentar harus punya tepat satu penulis: user_id atau resident_id.');
            }
        });
    }

    /** @return BelongsTo<Complaint, $this> */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public static function exportName(): string
    {
        return 'komentar_komplain';
    }

    public static function exportColumns(): array
    {
        return ['id', 'complaint_id', 'user_id', 'resident_id', 'body', 'created_at'];
    }
}
