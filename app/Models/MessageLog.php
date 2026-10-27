<?php

namespace App\Models;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Portal\PortalLinkResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Catatan pengiriman. Tidak memakai Auditable: tabel ini sendiri adalah jejak, dan isinya memuat nomor dan teks pesan.
 */
class MessageLog extends Model
{
    use BelongsToTenant, HasFactory;

    /** Pengiriman yang gagal dicoba paling banyak sekian kali sebelum berstatus failed. */
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'resident_id',
        'subject_type',
        'subject_id',
        'channel',
        'category',
        'recipient',
        'body',
        'status',
        'skip_reason',
        'provider',
        'provider_message_id',
        'error_code',
        'error_message',
        'attempts',
        'scheduled_for',
        'sent_at',
        'delivered_at',
        'failed_at',
        'counted_in_quota',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * Isi pesan untuk ditampilkan: tautan pribadi disembunyikan (token asli tidak pernah disimpan di log).
     */
    public function displayBody(): string
    {
        return PortalLinkResolver::mask($this->body);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Hanya pesan gagal yang punya nomor penerima. Gagal karena nomor tidak valid (recipient kosong) tidak
     * dikirim ulang: nomornya harus diperbaiki di data penghuni, lalu pesan berikutnya dibuat dari pemicunya.
     */
    public function canResend(): bool
    {
        return $this->status === MessageStatus::Failed && $this->recipient !== '';
    }
}
