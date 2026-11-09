<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMismatchException;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model milik pemilik kos: global scope tenant_id dan pengisian otomatis.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $contextId = TenantContext::instance()->id();

            if ($contextId === null) {
                return;
            }

            if ($model->tenant_id === null) {
                $model->tenant_id = $contextId;
            } elseif ((int) $model->tenant_id !== $contextId) {
                throw new TenantMismatchException('tenant_id tidak sama dengan tenant aktif.');
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
