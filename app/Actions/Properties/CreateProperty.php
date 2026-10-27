<?php

namespace App\Actions\Properties;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\Property;
use App\Services\Plans\PlanLimits;
use Illuminate\Support\Facades\DB;

class CreateProperty
{
    use ResolvesTenant;

    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): Property
    {
        $tenant = $this->currentTenant();

        return DB::transaction(function () use ($tenant, $data) {
            $this->limits->lockTenant($tenant);
            $this->limits->ensureCanAddProperties($tenant);

            return Property::create($data);
        });
    }
}
