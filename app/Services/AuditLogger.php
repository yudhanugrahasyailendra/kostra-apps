<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(string $event, ?Model $subject = null, ?array $old = null, ?array $new = null): AuditLog
    {
        $tenantId = TenantContext::instance()->id();

        if ($tenantId === null && $subject !== null) {
            // Model Tenant sendiri tidak punya tenant_id; kuncinya adalah id-nya.
            $tenantId = $subject->getAttribute('tenant_id') ?? ($subject instanceof Tenant ? $subject->getKey() : null);
        }

        $request = app()->bound('request') ? request() : null;

        // Alasan unscoped: pencatatan audit harus berhasil juga di luar konteks
        // tenant (pendaftaran, penjadwal, platform) dan tidak boleh gagal diam-diam.
        return TenantContext::instance()->unscoped(fn () => AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
        ]));
    }
}
