<?php

namespace Tests\Feature\Hardening;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, MessageTemplateSeeder::class]);
    }

    public function test_demo_seed_membuat_tenant_dengan_tagihan_dan_pembayaran_konsisten(): void
    {
        $this->artisan('demo:seed', ['--rooms' => 10, '--months' => 3, '--email' => 'demo@kostra.test', '--password' => 'rahasia-demo-123'])
            ->assertSuccessful();

        $tenant = Tenant::query()->firstOrFail();

        TenantContext::instance()->runAs($tenant, function () {
            $this->assertSame(3, Invoice::query()->distinct()->count('period'));
            $this->assertGreaterThan(0, Payment::query()->count());

            // paid_total tiap tagihan sama dengan jumlah alokasi pembayaran aktif.
            Invoice::query()->get()->each(function (Invoice $invoice) {
                $this->assertSame(
                    (int) PaymentAllocation::query()->where('invoice_id', $invoice->id)->whereHas('payment', fn ($q) => $q->where('status', 'active'))->sum('amount'),
                    (int) $invoice->paid_total,
                );
            });
        });
    }

    public function test_demo_seed_idempoten_terhadap_penjadwal(): void
    {
        $this->artisan('demo:seed', ['--rooms' => 6, '--months' => 2, '--password' => 'rahasia-demo-123'])->assertSuccessful();

        $this->artisan('invoices:generate')->assertSuccessful();
        $before = TenantContext::instance()->unscoped(fn () => Invoice::query()->count());
        $this->artisan('invoices:generate')->assertSuccessful();

        $this->assertSame($before, TenantContext::instance()->unscoped(fn () => Invoice::query()->count()));
    }

    public function test_demo_seed_menolak_email_yang_sudah_ada_dan_production_tanpa_force(): void
    {
        $existing = Tenant::factory()->create();
        User::factory()->forTenant($existing)->owner()->create(['email' => 'demo@kostra.test']);

        $this->artisan('demo:seed', ['--rooms' => 5, '--months' => 1])->assertFailed();

        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('demo:seed', ['--email' => 'lain@kostra.test'])->assertFailed();
        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_demo_seed_menolak_penyedia_whatsapp_sungguhan(): void
    {
        config(['kostra.whatsapp.provider' => 'meta']);

        $this->artisan('demo:seed', ['--rooms' => 5, '--months' => 1])->assertFailed();
        $this->assertSame(0, Tenant::query()->count());
    }
}
