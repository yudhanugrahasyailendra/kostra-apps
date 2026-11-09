<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Exports\TenantExportable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements TenantExportable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToTenant, HasFactory, Notifiable, SoftDeletes;

    // tenant_id, role, dan is_active sengaja tidak mass-assignable.
    protected $fillable = ['name', 'email', 'phone', 'password', 'permissions'];

    protected $hidden = ['password', 'remember_token'];

    /** Kolom yang tidak masuk jejak audit. */
    protected array $auditExclude = ['password', 'remember_token', 'last_login_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function hasPermission(Permission $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return in_array($permission->value, $this->permissions ?? [], true);
    }

    public static function exportName(): string
    {
        return 'pengguna';
    }

    public static function exportColumns(): array
    {
        return ['id', 'name', 'email', 'phone', 'role', 'permissions', 'is_active', 'last_login_at', 'created_at'];
    }
}
