<?php

namespace App\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class SuspendTenant
{
    /** Tenant ditangguhkan hanya bisa membaca dan mengekspor (LGN-07). Aman dipanggil berulang. */
    public function handle(Tenant $tenant): Tenant
    {
        // Tenant yang sengaja ditutup tidak berubah menjadi ditangguhkan.
        if ($tenant->status === TenantStatus::Closed) {
            throw ValidationException::withMessages(['tenant' => 'Tenant ini sudah ditutup dan tidak bisa ditangguhkan.']);
        }

        if ($tenant->status === TenantStatus::Suspended) {
            return $tenant;
        }

        $tenant->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => now()])->save();

        return $tenant;
    }
}
