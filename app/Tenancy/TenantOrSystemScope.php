<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Untuk tabel yang memuat baris milik tenant dan baris bawaan sistem (tenant_id null), seperti message_templates.
 * Tenant melihat barisnya sendiri ditambah baris sistem, tidak pernah baris tenant lain.
 */
class TenantOrSystemScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isUnscoped()) {
            return;
        }

        if ($context->id() === null) {
            // Gagal tertutup, sama seperti TenantScope.
            $builder->whereRaw('0 = 1');

            return;
        }

        $column = $model->qualifyColumn('tenant_id');

        $builder->where(fn (Builder $query) => $query->where($column, $context->id())->orWhereNull($column));
    }
}
