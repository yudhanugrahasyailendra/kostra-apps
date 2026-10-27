<?php

namespace App\Console\Commands;

use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KostraCheck extends Command
{
    protected $signature = 'kostra:check';

    protected $description = 'Memeriksa kesiapan konfigurasi sebelum dipakai (APP_KEY, debug, HTTPS, cookie, antrean, database, seeder, penyimpanan). Gagal bila ada pemeriksaan yang tidak lulus.';

    public function handle(TenantContext $context): int
    {
        $production = app()->isProduction();
        $rows = [];

        $rows[] = ['APP_KEY terisi', filled(config('app.key')), 'Jalankan php artisan key:generate'];
        $rows[] = ['APP_DEBUG mati', ! config('app.debug') || ! $production, 'Set APP_DEBUG=false di production'];
        $rows[] = ['APP_URL memakai https', ! $production || str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL=https://...'];
        $rows[] = ['Cookie sesi aman', ! $production || config('session.secure') === true, 'Set SESSION_SECURE_COOKIE=true'];
        $rows[] = ['Sesi terenkripsi', ! $production || config('session.encrypt') === true, 'Set SESSION_ENCRYPT=true'];
        $rows[] = ['Antrean bukan sync', config('queue.default') !== 'sync' || ! $production, 'Pakai QUEUE_CONNECTION=database atau redis'];
        $rows[] = ['Mailer bukan log/array', ! $production || ! in_array(config('mail.default'), ['log', 'array'], true), 'Isi MAIL_MAILER nyata: undangan dan reset kata sandi memuat tautan rahasia'];
        $rows[] = ['WhatsApp: penyedia dikenal', in_array(config('kostra.whatsapp.provider'), ['fake'], true) || ! $production, 'Penyedia nyata belum tersedia; production sementara memakai fake'];
        $rows[] = ['Rekening langganan terisi', filled(config('kostra.subscription.account_number')) && filled(config('kostra.subscription.account_name')), 'Isi SUBSCRIPTION_BANK/ACCOUNT_NUMBER/ACCOUNT_NAME'];

        $rows[] = ['Database terhubung', $this->attempt(fn () => DB::select('select 1') !== []), 'Periksa DB_* di .env'];
        $rows[] = ['Migrasi sudah dijalankan', $this->attempt(fn () => $this->pendingMigrations() === 0), 'Jalankan php artisan migrate --force'];
        $rows[] = ['Paket (plans) terisi', $this->attempt(fn () => Plan::query()->where('key', 'free')->exists()), 'Jalankan php artisan db:seed --class=PlanSeeder'];
        $rows[] = ['Templat pesan sistem terisi', $this->attempt(fn () => $context->withoutTenant(fn () => MessageTemplate::query()->whereNull('tenant_id')->exists())), 'Jalankan php artisan db:seed --class=MessageTemplateSeeder'];
        $rows[] = ['Penyimpanan privat bisa ditulis', $this->attempt(function () {
            $name = '.kostra-check-'.bin2hex(random_bytes(4));
            Storage::disk('private')->put($name, 'ok');

            return tap(Storage::disk('private')->get($name) === 'ok', fn () => Storage::disk('private')->delete($name));
        }), 'Periksa izin storage/app/private'];

        $this->table(['Pemeriksaan', 'Hasil'], array_map(fn (array $r) => [$r[0], $r[1] ? 'OK' : 'GAGAL - '.$r[2]], $rows));

        $failed = count(array_filter($rows, fn (array $r) => ! $r[1]));
        $failed === 0 ? $this->info('Semua pemeriksaan lulus.') : $this->error("{$failed} pemeriksaan gagal.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function attempt(callable $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable) {
            return false;
        }
    }

    private function pendingMigrations(): int
    {
        $migrator = app(Migrator::class);
        $files = $migrator->getMigrationFiles($migrator->paths() + [database_path('migrations')]);

        return count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }
}
