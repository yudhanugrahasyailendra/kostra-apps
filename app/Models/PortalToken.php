<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Tautan portal penghuni. Hanya hash SHA-256 token yang disimpan. Mencari token dari tautan dilakukan sebelum
 * tenant diketahui, jadi hanya ResolvePortalToken yang membacanya di luar konteks tenant.
 */
class PortalToken extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Token asli: 64 karakter alfanumerik acak (sekitar 380 bit), tidak bisa ditebak. */
    public const LENGTH = 64;

    protected array $auditExtraExclude = ['token_hash'];

    protected $fillable = [
        'tenant_id',
        'resident_id',
        'token_hash',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function generate(): string
    {
        return Str::random(self::LENGTH);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Bentuk token yang sah. Nilai lain ditolak sebelum menyentuh database.
     */
    public static function looksValid(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{'.self::LENGTH.'}$/', $token) === 1;
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query, ?CarbonInterface $now = null): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now ?? now()));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    public function isUsable(?CarbonInterface $now = null): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired($now);
    }
}
