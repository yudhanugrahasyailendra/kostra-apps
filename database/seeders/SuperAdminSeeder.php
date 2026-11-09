<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Membuat satu Super Admin dari env KOSTRA_ADMIN_EMAIL dan KOSTRA_ADMIN_PASSWORD.
     * Tanpa kedua env itu, seeder dilewati (tidak ada kredensial bawaan).
     */
    public function run(): void
    {
        $email = env('KOSTRA_ADMIN_EMAIL');
        $password = env('KOSTRA_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('KOSTRA_ADMIN_EMAIL/KOSTRA_ADMIN_PASSWORD kosong: Super Admin dilewati.');

            return;
        }

        // Alasan unscoped: seeder berjalan tanpa tenant dan Super Admin tidak punya tenant.
        TenantContext::instance()->unscoped(function () use ($email, $password): void {
            $admin = User::withTrashed()->firstOrNew(['email' => $email]);
            $admin->fill(['name' => 'Super Admin Kostra', 'password' => $password]);
            $admin->role = UserRole::SuperAdmin;
            $admin->is_active = true;
            $admin->save();
        });
    }
}
