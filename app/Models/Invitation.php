<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Exports\TenantExportable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model implements TenantExportable
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['email', 'phone', 'role', 'permissions', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function exportName(): string
    {
        return 'undangan';
    }

    // token_hash sengaja tidak diekspor.
    public static function exportColumns(): array
    {
        return ['id', 'email', 'phone', 'role', 'permissions', 'expires_at', 'accepted_at', 'created_at'];
    }
}
