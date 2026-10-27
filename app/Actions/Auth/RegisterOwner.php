<?php

namespace App\Actions\Auth;

use App\Actions\Finance\SeedExpenseCategories;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterOwner
{
    /**
     * Membuat tenant (paket Gratis) dan pengguna pemilik dalam satu transaksi.
     *
     * @param  array{name: string, business_name: string, email?: ?string, phone?: ?string, password: string}  $data
     */
    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::query()->where('key', 'free')->firstOrFail();

            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => Str::slug($data['business_name']).'-'.Str::lower(Str::random(6)),
                'plan_id' => $plan->getKey(),
                'status' => TenantStatus::Active,
                'timezone' => config('kostra.default_timezone'),
            ]);

            // Pengaturan penagihan awal untuk tenant baru.
            BillingSetting::create([...BillingSetting::defaults(), 'tenant_id' => $tenant->getKey()]);

            // Kategori pengeluaran bawaan (listrik, air, gaji penjaga, dst.).
            app(TenantContext::class)->run($tenant, fn () => app(SeedExpenseCategories::class)());

            return User::create([
                'tenant_id' => $tenant->getKey(),
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => UserRole::Owner,
                'is_active' => true,
            ]);
        });
    }
}
