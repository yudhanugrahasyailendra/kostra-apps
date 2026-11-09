<?php

namespace Database\Factories;

use App\Models\LeaseResident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaseResident> */
class LeaseResidentFactory extends Factory
{
    public function definition(): array
    {
        return ['is_primary' => false];
    }
}
