<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);

            if ($context->isUnscoped() || $context->id() === null) {
                return;
            }

            if ($model->tenant_id === null) {
                $model->tenant_id = $context->id();

                return;
            }

            if ((int) $model->tenant_id !== $context->id()) {
                throw new LogicException('tenant_id tidak sama dengan tenant yang sedang aktif.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
