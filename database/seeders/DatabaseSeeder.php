<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            MessageTemplateSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Data dummy hanya untuk pengembangan lokal (akun demo@kostra.test).
        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
