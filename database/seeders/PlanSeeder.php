<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach (config('kostra.plans') as $key => $plan) {
            Plan::updateOrCreate(['key' => $key], [
                ...$plan,
                'price_yearly' => null,
                'features' => $plan['features'] ?? [],
                'is_active' => true,
                'sort_order' => $order++,
            ]);
        }
    }
}
