<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = TenantContext::instance();

        if ($context->isUnscoped()) {
            return;
        }

        $tenantId = $context->id();

        if ($tenantId === null) {
            // Gagal tertutup: tanpa tenant aktif tidak ada baris yang boleh terbaca.
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
