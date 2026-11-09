<?php

namespace App\Tenancy;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

/**
 * Autentikasi harus menemukan pengguna sebelum tenant diketahui, jadi query
 * pengguna di sini sengaja tanpa TenantScope. Tenant ditetapkan sesudahnya
 * oleh middleware SetCurrentTenant dari pengguna yang sudah terautentikasi.
 */
class TenantUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null): Builder
    {
        // Alasan withoutGlobalScope: lihat docblock kelas.
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
