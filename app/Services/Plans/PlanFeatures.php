<?php

namespace App\Services\Plans;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Pembatasan fitur lewat plans.features. Fitur yang tidak ada di paket menampilkan tawaran naik paket,
 * bukan galat teknis.
 */
class PlanFeatures
{
    public function has(Tenant $tenant, string $feature): bool
    {
        return in_array($feature, $tenant->plan->features ?? [], true);
    }

    /**
     * @throws ValidationException
     */
    public function ensure(Tenant $tenant, string $feature): void
    {
        if (! $this->has($tenant, $feature)) {
            $label = config("kostra.features.{$feature}", $feature);

            throw ValidationException::withMessages([
                'plan_limit' => "Fitur \"{$label}\" tidak termasuk paket {$tenant->plan->name}. Naik paket untuk memakainya.",
            ]);
        }
    }
}
