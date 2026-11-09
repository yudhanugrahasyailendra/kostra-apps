<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Demo\DemoTenantBuilder;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed
        {--rooms=30 : Jumlah kamar (maks. 100 pada paket Pro demo)}
        {--months=6 : Riwayat tagihan dalam bulan}
        {--email=demo@kostra.test : Email pemilik demo}
        {--password= : Kata sandi pemilik demo (acak bila kosong)}
        {--force : Izinkan di lingkungan production}';

    protected $description = 'Buat tenant demo dengan data realistis untuk mendemokan ke calon pelanggan';

    public function handle(DemoTenantBuilder $builder): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Menolak berjalan di production. Gunakan --force bila memang untuk server demo.');

            return self::FAILURE;
        }

        $email = (string) $this->option('email');

        // Pemeriksaan lintas tenant yang disengaja: email harus unik secara global, jadi kita periksa tanpa scope.
        if (TenantContext::instance()->unscoped(fn () => User::query()->where('email', $email)->exists())) {
            $this->error("Email $email sudah dipakai. Pakai --email lain; tenant lama tidak dihapus oleh perintah ini.");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));

        try {
            $result = $builder->build([
                'rooms' => (int) $this->option('rooms'),
                'months' => (int) $this->option('months'),
                'email' => $email,
                'password' => $password,
            ]);
        } catch (RuntimeException|ValidationException $e) {
            $this->error($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Tenant demo dibuat: {$result['tenant']->name}");
        $this->table(['Kamar', 'Kontrak', 'Tagihan', 'Pembayaran'], [[$result['rooms'], $result['leases'], $result['invoices'], $result['payments']]]);
        $this->line("Masuk dengan $email / $password");
        $this->line('Simpan kata sandi ini sekarang; tidak ditampilkan lagi.');

        return self::SUCCESS;
    }
}
