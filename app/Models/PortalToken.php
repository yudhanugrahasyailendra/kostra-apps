<?php

namespace App\Models;

use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PortalTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tautan portal penghuni. Hanya hash SHA-256 token yang disimpan. Sengaja tanpa Auditable: token dibuat
 * pada hampir setiap pesan WhatsApp sehingga audit_logs akan penuh; pencabutan dicatat terpisah.
 */
class PortalToken extends Model implements TenantExportable
{
    /** @use HasFactory<PortalTokenFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['resident_id', 'token_hash', 'expires_at', 'last_used_at', 'revoked_at', 'created_by'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Token acak 256 bit (hex 64 karakter), tidak dapat ditebak. */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @param  Builder<PortalToken>  $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public static function exportName(): string
    {
        return 'tautan_portal';
    }

    /** token_hash tidak diekspor. */
    public static function exportColumns(): array
    {
        return ['id', 'resident_id', 'expires_at', 'last_used_at', 'revoked_at', 'created_by', 'created_at'];
    }
}
