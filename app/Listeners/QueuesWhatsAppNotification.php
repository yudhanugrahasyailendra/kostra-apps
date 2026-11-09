<?php

namespace App\Listeners;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Dasar listener notifikasi. Tenant ditetapkan eksplisit dari model pemicu (tidak bergantung pada pengguna
 * login), dan kegagalan mengantrekan pesan dilaporkan tanpa menggagalkan alur bisnis yang sudah berhasil.
 */
abstract class QueuesWhatsAppNotification
{
    protected function inTenantOf(Model $model, callable $callback): void
    {
        $tenant = Tenant::query()->find($model->getAttribute('tenant_id'));

        if ($tenant === null) {
            return;
        }

        try {
            TenantContext::instance()->runAs($tenant, $callback);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
