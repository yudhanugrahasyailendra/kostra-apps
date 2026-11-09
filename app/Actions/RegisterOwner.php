<?php

namespace App\Actions;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterOwner
{
    /**
     * Membuat tenant paket Gratis dan pengguna pemilik dalam satu transaksi.
     *
     * @param  array{kos_name: string, name: string, email?: string|null, phone?: string|null, password: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::query()->where('key', 'free')->firstOrFail();

            $tenant = Tenant::create([
                'name' => $data['kos_name'],
                'slug' => $this->uniqueSlug($data['kos_name']),
                'plan_id' => $plan->id,
                'status' => TenantStatus::Active,
                'phone' => $data['phone'] ?? null,
                'timezone' => config('kostra.display_timezone'),
            ]);

            return TenantContext::instance()->runAs($tenant, function () use ($data) {
                $user = new User([
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                ]);
                $user->role = UserRole::Owner;
                $user->save();

                app(EnsureDefaultExpenseCategories::class)->handle();

                return $user;
            });
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kos';
        $slug = $base;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
