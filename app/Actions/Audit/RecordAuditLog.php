<?php

namespace App\Actions\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecordAuditLog
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __invoke(
        AuditEvent $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $tenantId = null,
    ): AuditLog {
        $tenantId ??= match (true) {
            $auditable instanceof Tenant => $auditable->getKey(),
            $auditable !== null && $auditable->getAttribute('tenant_id') !== null => (int) $auditable->getAttribute('tenant_id'),
            default => $this->context->id(),
        };

        $request = app()->runningInConsole() ? null : request();

        // Penulisan jejak audit lintas konteks disengaja: tenant_id diisi eksplisit di atas.
        return $this->context->withoutTenant(fn () => AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old === null ? null : $this->jsonSafe($old),
            'new_values' => $new === null ? null : $this->jsonSafe($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function jsonSafe(array $values): array
    {
        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }
}
