<?php

namespace App\Models;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MessageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Catatan setiap pesan keluar. Berisi nomor dan teks akhir sebagai bukti, jadi sengaja tidak memakai
 * Auditable (isinya tidak disalin ke audit_logs) dan tidak pernah ditulis ke log aplikasi.
 */
class MessageLog extends Model implements TenantExportable
{
    /** @use HasFactory<MessageLogFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'template_id', 'resident_id', 'subject_type', 'subject_id', 'channel', 'category', 'recipient', 'body',
        'status', 'skip_reason', 'provider', 'provider_message_id', 'error_code', 'error_message', 'attempts',
        'scheduled_for', 'sent_at', 'delivered_at', 'failed_at', 'counted_in_quota',
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'skip_reason' => MessageSkipReason::class,
            'attempts' => 'integer',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'counted_in_quota' => 'boolean',
        ];
    }

    /**
     * Ganti token portal di teks pesan dengan penanda. Log menyimpan teks akhir sebagai bukti, tetapi token
     * adalah kunci akses; setelah pesan terkirim (atau dilewati) tidak ada alasan menyimpannya lagi.
     */
    public function redactPortalTokens(): void
    {
        $redacted = preg_replace('#/p/[a-f0-9]{64}#', '/p/[tautan-rahasia]', $this->body);

        if ($redacted !== null && $redacted !== $this->body) {
            $this->forceFill(['body' => $redacted])->save();
        }
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function exportName(): string
    {
        return 'log_pesan';
    }

    public static function exportColumns(): array
    {
        return [
            'id', 'template_id', 'resident_id', 'subject_type', 'subject_id', 'channel', 'category', 'recipient', 'body',
            'status', 'skip_reason', 'provider', 'error_code', 'attempts', 'scheduled_for', 'sent_at', 'delivered_at',
            'failed_at', 'counted_in_quota', 'created_at',
        ];
    }
}
