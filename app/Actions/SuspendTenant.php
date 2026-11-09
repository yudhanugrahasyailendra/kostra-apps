<?php

namespace App\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;

class SuspendTenant
{
    /** Tenant ditangguhkan hanya bisa membaca dan mengekspor (LGN-07). Aman dipanggil berulang. */
    public function handle(Tenant $tenant): Tenant
    {
        if ($tenant->status === TenantStatus::Suspended) {
            return $tenant;
        }

        $tenant->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => now()])->save();

        return $tenant;
    }
}
