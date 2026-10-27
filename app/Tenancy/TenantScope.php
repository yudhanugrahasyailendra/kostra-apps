<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isUnscoped()) {
            return;
        }

        if ($context->id() === null) {
            // Gagal tertutup: tanpa tenant tidak ada data yang terlihat.
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $context->id());
    }
}
