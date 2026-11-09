<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Aturan validasi basis data yang selalu dibatasi tenant aktif. Rule::exists dan
 * Rule::unique memakai query builder (tanpa TenantScope), jadi tenant_id wajib
 * ditambahkan di sini; jangan memakai keduanya langsung untuk tabel tenant.
 */
final class TenantRules
{
    public static function exists(string $table, bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, 'id')->where('tenant_id', TenantContext::instance()->id());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    /** Unik di dalam tenant aktif; $scope = kolom tambahan yang harus sama (mis. property_id). */
    public static function unique(string $table, string $column, array $scope = [], bool $softDeletes = false, ?int $ignoreId = null): Unique
    {
        $rule = Rule::unique($table, $column)->where(function ($query) use ($scope, $softDeletes) {
            $query->where('tenant_id', TenantContext::instance()->id());

            foreach ($scope as $key => $value) {
                $value === null ? $query->whereNull($key) : $query->where($key, $value);
            }

            if ($softDeletes) {
                $query->whereNull('deleted_at');
            }
        });

        return $ignoreId !== null ? $rule->ignore($ignoreId) : $rule;
    }
}
