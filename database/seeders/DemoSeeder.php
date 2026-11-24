<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Demo\DemoTenantBuilder;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@kostra.test';

    public const PASSWORD = 'demo12345';

    /**
     * Tenant demo untuk pengembangan lokal, dibuat lewat DemoTenantBuilder (Action yang sama dengan
     * aplikasi). Hanya di lingkungan local: kata sandinya tetap dan tertulis di kode. Untuk server demo
     * pakai `php artisan demo:seed` dengan kata sandi acak. Bila email sudah ada, seeder dilewati.
     */
    public function run(DemoTenantBuilder $builder): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('DemoSeeder hanya berjalan di APP_ENV=local: dilewati.');

            return;
        }

        // Alasan unscoped: email pengguna unik secara global, pemeriksaan lintas tenant disengaja.
        if (TenantContext::instance()->unscoped(fn () => User::query()->withTrashed()->where('email', self::EMAIL)->exists())) {
            $this->command?->warn('Akun '.self::EMAIL.' sudah ada: data demo dilewati.');

            return;
        }

        $result = $builder->build([
            'rooms' => 30,
            'months' => 6,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        $this->command?->info("Tenant demo dibuat: {$result['tenant']->name} ({$result['rooms']} kamar, {$result['leases']} kontrak, {$result['invoices']} tagihan, {$result['payments']} pembayaran).");
        $this->command?->info('Masuk dengan '.self::EMAIL.' / '.self::PASSWORD);
    }
}
