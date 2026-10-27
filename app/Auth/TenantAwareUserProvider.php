<?php

namespace App\Auth;

use App\Tenancy\TenantScope;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Pengguna dimuat dari sesi, token ingat, atau kredensial sebelum konteks tenant ada.
 * Karena itu provider autentikasi (dan hanya ini) melewati TenantScope.
 */
class TenantAwareUserProvider extends EloquentUserProvider
{
    public function newModelQuery($model = null)
    {
        // Disengaja: identitas pengguna harus bisa dicari sebelum tenant diketahui.
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
