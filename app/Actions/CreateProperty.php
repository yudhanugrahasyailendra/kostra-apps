<?php

namespace App\Actions;

use App\Models\Property;
use App\Services\Plans\PlanLimits;

class CreateProperty
{
    public function __construct(private PlanLimits $limits) {}

    /** @param  array<string, mixed>  $data */
    public function handle(array $data): Property
    {
        return $this->limits->withinLock(function () use ($data) {
            $this->limits->assertCanAddProperty();

            return Property::create($data);
        });
    }
}
