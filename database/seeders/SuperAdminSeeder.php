<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('kostra.super_admin.email');
        $password = config('kostra.super_admin.password');

        if (! $email || ! $password) {
            throw new RuntimeException('Isi SUPERADMIN_EMAIL dan SUPERADMIN_PASSWORD di .env sebelum menyemai Super Admin.');
        }

        // Super Admin tidak punya tenant, jadi dibuat di luar konteks tenant secara eksplisit.
        app(TenantContext::class)->withoutTenant(function () use ($email, $password) {
            User::firstOrCreate(['email' => $email], [
                'name' => config('kostra.super_admin.name'),
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]);
        });
    }
}
