<?php

namespace App\Actions\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use LogicException;

trait ResolvesTenant
{
    protected function currentTenant(): Tenant
    {
        return app(TenantContext::class)->current()
            ?? throw new LogicException('Action ini harus dijalankan dalam konteks tenant.');
    }
}
