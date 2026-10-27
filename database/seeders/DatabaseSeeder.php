<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);
        $this->call(MessageTemplateSeeder::class);

        if (config('kostra.super_admin.email') && config('kostra.super_admin.password')) {
            $this->call(SuperAdminSeeder::class);
        } else {
            $this->command?->warn('SUPERADMIN_EMAIL/SUPERADMIN_PASSWORD belum diisi; Super Admin dilewati.');
        }
    }
}
