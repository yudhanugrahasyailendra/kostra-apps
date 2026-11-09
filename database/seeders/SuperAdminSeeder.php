<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Membuat satu Super Admin dari KOSTRA_ADMIN_EMAIL dan KOSTRA_ADMIN_PASSWORD
     * (lewat config/kostra.php). Tanpa kedua nilai itu, seeder dilewati.
     * Seeder menolak memakai email milik akun tenant: Super Admin tidak pernah
     * dibuat dari pengguna tenant yang sudah ada.
     */
    public function run(): void
    {
        $email = config('kostra.admin.email');
        $password = config('kostra.admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('KOSTRA_ADMIN_EMAIL/KOSTRA_ADMIN_PASSWORD kosong: Super Admin dilewati.');

            return;
        }

        // Alasan unscoped: seeder berjalan tanpa tenant dan Super Admin tidak punya tenant.
        TenantContext::instance()->unscoped(function () use ($email, $password): void {
            $existing = User::withTrashed()->where('email', $email)->first();

            if ($existing !== null && ($existing->role !== UserRole::SuperAdmin || $existing->tenant_id !== null)) {
                throw new RuntimeException('Email Super Admin sudah dipakai akun tenant. Pilih email lain.');
            }

            $admin = $existing ?? new User(['email' => $email]);
            $admin->fill(['name' => 'Super Admin Kostra', 'password' => $password]);
            $admin->role = UserRole::SuperAdmin;
            $admin->tenant_id = null;
            $admin->is_active = true;
            $admin->deleted_at = null;
            $admin->save();
        });
    }
}
