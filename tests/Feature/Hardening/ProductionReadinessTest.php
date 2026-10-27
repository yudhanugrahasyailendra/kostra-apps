<?php

namespace Tests\Feature\Hardening;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PortalFixtures;
use Tests\TestCase;

/**
 * Fase 12: indeks terdokumentasi, job, penjadwal, log tanpa data pribadi, performa, header, kostra:check, demo:seed.
 */
class ProductionReadinessTest extends TestCase
{
    use PortalFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpPortal();
        $this->seed(MessageTemplateSeeder::class);
    }

    // ---- Indeks (docs/database.md bagian 11) ----

    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns && (! $unique || $index['unique'])) {
                return true;
            }
        }

        return false;
    }

    public function test_the_documented_indexes_and_unique_constraints_exist(): void
    {
        $expected = [
            ['leases', ['active_room_id'], true],
            ['invoices', ['lease_id', 'period'], true],
            ['invoices', ['tenant_id', 'number'], true],
            ['invoices', ['tenant_id', 'status', 'due_date'], false],
            ['invoices', ['tenant_id', 'resident_id', 'status'], false],
            ['leases', ['tenant_id', 'status', 'end_date'], false],
            ['payment_proofs', ['tenant_id', 'status', 'created_at'], false],
            ['payments', ['tenant_id', 'paid_on'], false],
            ['payments', ['tenant_id', 'receipt_number'], true],
            ['expenses', ['tenant_id', 'spent_on'], false],
            ['residents', ['tenant_id', 'phone'], false],
            ['residents', ['tenant_id', 'name'], false],
            ['rooms', ['tenant_id', 'property_id', 'status'], false],
            ['usage_counters', ['tenant_id', 'metric', 'period'], true],
            ['message_logs', ['provider_message_id'], false],
            ['portal_tokens', ['token_hash'], true],
            ['invitations', ['token_hash'], true],
            ['number_sequences', ['tenant_id', 'key', 'period'], true],
            ['payment_allocations', ['payment_id', 'invoice_id'], true],
            ['audit_logs', ['tenant_id', 'created_at'], false],
        ];

        foreach ($expected as [$table, $columns, $unique]) {
            $this->assertTrue($this->hasIndex($table, $columns, $unique), "Indeks hilang: {$table}(".implode(', ', $columns).')'.($unique ? ' unik' : ''));
        }
    }

    // ---- Job ----

    public function test_every_job_has_bounded_retries_and_failed_jobs_are_recorded(): void
    {
        $this->assertTrue(Schema::hasTable('failed_jobs'));

        $files = glob(app_path('Jobs/*.php'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'App\\Jobs\\'.basename($file, '.php');
            $job = (new \ReflectionClass($class));

            $this->assertTrue(
                $job->hasProperty('tries') || $job->hasMethod('retryUntil'),
                "{$class} tidak membatasi percobaan ulang (\$tries atau retryUntil).",
            );
        }
    }

    // ---- Log tanpa data pribadi ----

    public function test_application_code_does_not_write_personal_data_to_logs(): void
    {
        $allowed = ['app/Actions/Billing/GenerateMonthlyInvoices.php', 'app/Services/Messaging/Listeners/', 'bootstrap/app.php'];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

            foreach (file($file->getPathname()) as $n => $line) {
                if (preg_match('/\b(dd|dump|var_dump|print_r|error_log)\(|\blogger\(|\breport\(|\bLog::/', $line)) {
                    $isAllowed = collect($allowed)->contains(fn ($a) => str_starts_with($path, $a));
                    $this->assertTrue($isAllowed, "{$path}:".($n + 1).' menulis ke log di luar daftar yang ditinjau.');
                    $this->assertDoesNotMatchRegularExpression('/getMessage|->phone|->name|->email|recipient|->body|getTraceAsString/', $line, "{$path}:".($n + 1).' memuat kemungkinan data pribadi.');
                }
            }
        }
    }

    // ---- Penjadwal idempoten ----

    public function test_running_the_scheduled_commands_twice_creates_no_duplicates(): void
    {
        foreach (range(1, 3) as $i) {
            $this->leaseFor();
        }
        $this->invoiceDueDaysAgo(3);

        $snapshot = fn () => $this->withoutTenant(fn () => [
            Invoice::count(), MessageLog::count(), Subscription::count(),
            DB::table('subscription_invoices')->count(),
        ]);

        foreach (['billing:run', 'subscriptions:run', 'messages:reminders'] as $command) {
            Artisan::call($command);
            $first = $snapshot();
            Artisan::call($command);
            $this->assertSame($first, $snapshot(), "{$command} dua kali menghasilkan data ganda.");
        }

        $this->assertGreaterThan(0, $this->withoutTenant(fn () => Invoice::count()));
        $this->assertSame(0, $this->withoutTenant(fn () => Invoice::query()->select('lease_id', 'period')->groupBy('lease_id', 'period')->havingRaw('count(*) > 1')->get()->count()));
    }

    // ---- Performa ----

    /**
     * @return array{0: int, 1: float} jumlah query dan detik untuk satu GET
     */
    private function measure(string $route): array
    {
        $this->actingAs($this->owner)->get(route($route))->assertOk(); // pemanasan (sesi, cache)

        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);
        $this->actingAs($this->owner)->get(route($route))->assertOk();
        $seconds = microtime(true) - $start;
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$count, $seconds];
    }

    public function test_list_pages_do_not_run_more_queries_as_data_grows_and_stay_fast(): void
    {
        $routes = ['owner.dashboard', 'owner.rooms.index', 'owner.residents.index', 'owner.invoices.index', 'owner.payments.index'];

        foreach (range(1, 3) as $i) {
            $this->invoiceDueDaysAgo($i);
        }
        $this->record([[$this->invoiceDueDaysAgo(1), 100_000]]); // halaman kosong melewati eager load; baseline butuh satu baris
        $small = collect($routes)->mapWithKeys(fn ($r) => [$r => $this->measure($r)[0]]);

        foreach (range(1, 40) as $i) {
            $this->record([[$this->invoiceDueDaysAgo(($i % 20) + 1), 100_000]]);
        }

        foreach ($routes as $route) {
            [$count, $seconds] = $this->measure($route);

            $this->assertLessThanOrEqual($small[$route] + 2, $count, "{$route}: jumlah query naik dari {$small[$route]} ke {$count} (N+1).");
            $this->assertLessThan(2.0, $seconds, "{$route} lebih dari 2 detik.");
        }
    }

    // ---- Header keamanan ----

    public function test_security_headers_are_sent_and_hsts_only_over_https(): void
    {
        $plain = $this->get('/login');
        $plain->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Referrer-Policy');
        $plain->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    // ---- kostra:check ----

    public function test_the_preflight_check_passes_on_a_ready_setup_and_fails_on_an_unsafe_production(): void
    {
        $this->seed(PlanSeeder::class);
        config(['kostra.subscription.account_number' => '123', 'kostra.subscription.account_name' => 'PT Kostra']);

        $this->assertSame(0, Artisan::call('kostra:check'), Artisan::output());

        $this->app['env'] = 'production';
        config(['app.debug' => true, 'queue.default' => 'sync', 'app.url' => 'http://kostra.test', 'session.secure' => null]);
        $this->assertSame(1, Artisan::call('kostra:check'));
        $this->assertStringContainsString('GAGAL', Artisan::output());

        config(['app.debug' => false, 'queue.default' => 'database', 'app.url' => 'https://kostra.test', 'session.secure' => true, 'session.encrypt' => true, 'mail.default' => 'smtp']);
        $this->assertSame(0, Artisan::call('kostra:check'), Artisan::output());

        $this->app['env'] = 'testing';
    }

    // ---- demo:seed ----

    public function test_demo_seed_builds_one_realistic_tenant_and_refuses_to_run_twice(): void
    {
        $this->seed(PlanSeeder::class);
        $before = Tenant::count();

        $this->assertSame(0, Artisan::call('demo:seed', ['--password' => 'demo-rahasia-123']), Artisan::output());

        $this->assertSame($before + 1, Tenant::count());
        $demo = $this->withoutTenant(fn () => User::where('email', 'demo@kostra.test')->firstOrFail()->tenant);
        $this->assertSame('business', $demo->plan->key);

        $this->asTenant($demo, function () {
            $this->assertSame(12, Room::count());
            $this->assertSame(10, Resident::count());
            $this->assertGreaterThanOrEqual(30, Invoice::count());
            $this->assertGreaterThan(0, Payment::count());
        });

        // Semua baris milik satu tenant: tenant lain tidak tersentuh.
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Room::count()));

        $this->post('/login', ['login' => 'demo@kostra.test', 'email' => 'demo@kostra.test', 'password' => 'demo-rahasia-123']);

        $this->assertSame(1, Artisan::call('demo:seed'));
        $this->assertSame($before + 1, Tenant::count());
    }

    public function test_demo_seed_refuses_production_without_force(): void
    {
        $this->seed(PlanSeeder::class);
        $this->app['env'] = 'production';
        $before = Tenant::count();

        $this->assertSame(1, Artisan::call('demo:seed'));
        $this->assertSame($before, Tenant::count());

        $this->app['env'] = 'testing';
    }
}
