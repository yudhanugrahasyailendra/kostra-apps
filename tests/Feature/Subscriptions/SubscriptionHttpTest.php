<?php

namespace Tests\Feature\Subscriptions;

use App\Actions\ChangeSubscriptionPlan;
use App\Actions\ProcessSubscriptions;
use App\Actions\RegisterOwner;
use App\Actions\SuspendTenant;
use App\Enums\SubscriptionPeriod;
use App\Enums\TenantStatus;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class SubscriptionHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    /** @return array{0: Tenant, 1: User} */
    private function tenantWithOwner(string $name = 'Kos Uji', ?string $plan = null): array
    {
        $user = app(RegisterOwner::class)->handle([
            'kos_name' => $name, 'name' => 'Pemilik '.$name, 'email' => fake()->unique()->safeEmail(), 'password' => 'rahasia-banget',
        ]);
        $tenant = Tenant::query()->findOrFail($user->tenant_id);

        if ($plan !== null) {
            app(ChangeSubscriptionPlan::class)->handle($tenant, Plan::query()->where('key', $plan)->firstOrFail(), SubscriptionPeriod::Monthly);
        }

        return [$tenant->refresh(), User::factory()->forTenant($tenant)->owner()->create()];
    }

    private function pendingInvoice(Tenant $tenant): SubscriptionInvoice
    {
        return TenantContext::instance()->runAs($tenant, fn () => SubscriptionInvoice::query()->where('status', 'pending')->latest('id')->firstOrFail());
    }

    // --- Akses panel Super Admin ---

    public function test_hanya_super_admin_yang_bisa_membuka_admin(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $staff = User::factory()->forTenant($tenant)->staff(['finance.view'])->create();
        $admin = User::factory()->superAdmin()->create();

        $this->get(route('admin.tenants.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.tenants.show', $tenant))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.tenants.suspend', $tenant))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.tenants.activate', $tenant))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertOk();
    }

    public function test_super_admin_tidak_bisa_membuka_panel_pemilik(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('owner.dashboard'))->assertForbidden();
    }

    public function test_super_admin_nonaktif_ditolak(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_active' => false]);

        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertForbidden();
    }

    public function test_login_super_admin_diarahkan_ke_panel_admin(): void
    {
        User::factory()->superAdmin()->create(['email' => 'admin@kostra.test']);

        $this->post(route('login'), ['login' => 'admin@kostra.test', 'password' => 'password'])
            ->assertRedirect(route('admin.tenants.index'));
    }

    // --- Daftar tenant, pencarian, props ---

    public function test_daftar_tenant_menampilkan_langganan_dan_pemakaian_tanpa_kolom_terlarang(): void
    {
        [$tenant] = $this->tenantWithOwner('Kos Mawar', 'starter');
        $this->tenantWithOwner('Kos Melati');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tenants/Index')
                ->has('tenants', 2)
                ->where('tenants.1.name', 'Kos Mawar')
                ->where('tenants.1.plan_name', 'Starter')
                ->where('tenants.1.subscription_status', 'trialing')
                ->where('tenants.1.usage.rooms', 0)
                ->where('tenants.1.usage.max_rooms', 30)
                ->missing('tenants.1.settings')
                ->missing('tenants.1.logo_path')
                ->missing('tenants.1.password'));
    }

    public function test_pencarian_satu_langkah_lewat_nama_dan_kontak_pemilik(): void
    {
        $this->tenantWithOwner('Kos Mawar');
        $this->tenantWithOwner('Kos Melati');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.tenants.index', ['q' => 'mawar']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tenants', 1)->where('tenants.0.name', 'Kos Mawar'));

        $email = TenantContext::instance()->unscoped(fn () => User::query()->whereHas('tenant', fn ($q) => $q->where('name', 'Kos Melati'))->value('email'));
        $this->actingAs($admin)->get(route('admin.tenants.index', ['q' => $email]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tenants', 1)->where('tenants.0.name', 'Kos Melati'));

        $this->actingAs($admin)->get(route('admin.tenants.index', ['status' => 'suspended']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tenants', 0));
    }

    // --- Tangguhkan, aktifkan, bayar dari panel ---

    public function test_tangguhkan_dan_aktifkan_dari_panel(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.tenants.suspend', $tenant))->assertRedirect();
        $this->assertTrue($tenant->refresh()->isSuspended());

        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Baru'])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.tenants.activate', $tenant))->assertRedirect();
        $this->assertFalse($tenant->refresh()->isSuspended());
    }

    public function test_super_admin_mencatat_pembayaran_manual_dari_panel(): void
    {
        [$tenant] = $this->tenantWithOwner('Kos Mawar', 'pro');
        $invoice = $this->pendingInvoice($tenant);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.tenants.invoices.pay', [$tenant, $invoice->id]), [])
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($admin)->post(route('admin.tenants.invoices.pay', [$tenant, $invoice->id]), [
            'payment_method' => 'Transfer BCA', 'reference' => 'TRX-77',
        ])->assertRedirect()->assertSessionHas('success');

        $invoice = TenantContext::instance()->runAs($tenant, fn () => $invoice->fresh());
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame('TRX-77', $invoice->reference);
    }

    public function test_tagihan_tenant_lain_tidak_bisa_dibayar_lewat_url_tenant_berbeda(): void
    {
        [$a] = $this->tenantWithOwner('Kos A', 'starter');
        [$b] = $this->tenantWithOwner('Kos B', 'starter');
        $invoiceB = $this->pendingInvoice($b);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.tenants.invoices.pay', [$a, $invoiceB->id]), ['payment_method' => 'Transfer'])
            ->assertNotFound();

        $this->assertSame('pending', TenantContext::instance()->runAs($b, fn () => $invoiceB->fresh())->status->value);
    }

    // --- Halaman Paket (pemilik) ---

    public function test_halaman_paket_hanya_memuat_data_tenant_sendiri(): void
    {
        [$a, $ownerA] = $this->tenantWithOwner('Kos A', 'starter');
        [$b] = $this->tenantWithOwner('Kos B', 'pro');
        $numberB = $this->pendingInvoice($b)->number;

        $this->actingAs($ownerA)->get(route('owner.subscription.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Subscription/Index')
                ->where('currentPlanKey', 'starter')
                ->has('invoices', 1)
                ->where('invoices.0.number', $this->pendingInvoice($a)->number)
                ->where('subscription.status', 'trialing')
                ->where('trialAvailable', false)
                ->missing('invoices.0.tenant_id')
                ->missing('subscription.tenant_id'));

        $this->assertStringNotContainsString($numberB, $this->actingAs($ownerA)->get(route('owner.subscription.index'))->getContent());
    }

    public function test_staf_tidak_bisa_melihat_atau_mengubah_paket(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $staff = User::factory()->forTenant($tenant)->staff(['finance.view', 'settings.manage'])->create();

        $this->actingAs($staff)->get(route('owner.subscription.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('owner.subscription.update'), ['plan_key' => 'pro', 'billing_period' => 'monthly'])->assertForbidden();
    }

    public function test_pemilik_memilih_paket_dengan_validasi_bahasa_indonesia(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.subscription.update'), [])
            ->assertSessionHasErrors(['plan_key' => 'Pilih paket.', 'billing_period' => 'Pilih periode tagihan.']);
        $this->actingAs($owner)->post(route('owner.subscription.update'), ['plan_key' => 'tidak-ada', 'billing_period' => 'monthly'])
            ->assertSessionHasErrors(['plan_key' => 'Paket tidak tersedia.']);
    }

    public function test_turun_paket_lewat_halaman_menampilkan_tawaran_bukan_galat_teknis(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('Kos A', 'pro');
        TenantContext::instance()->runAs($tenant, function () {
            Property::factory()->count(2)->create();
        });

        $this->actingAs($owner)->post(route('owner.subscription.update'), ['plan_key' => 'free', 'billing_period' => 'monthly'])
            ->assertSessionHasErrors('limit');
        $this->assertSame('pro', $tenant->refresh()->plan->key);
    }

    // --- Batas staf dan fitur ---

    public function test_batas_pengguna_paket_menolak_undangan_staf(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner(); // Gratis: 1 pengguna (pemilik)

        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'staf@contoh.id'])
            ->assertSessionHasErrors('limit');

        $this->assertSame(0, TenantContext::instance()->runAs($tenant, fn () => Invitation::query()->count()));
    }

    public function test_paket_pro_menerima_undangan_staf(): void
    {
        [, $owner] = $this->tenantWithOwner('Kos Pro', 'pro');

        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'staf@contoh.id'])
            ->assertSessionHasNoErrors();
    }

    public function test_tagihan_otomatis_dan_pengingat_hanya_untuk_paket_dengan_fiturnya(): void
    {
        [$free] = $this->tenantWithOwner('Kos Gratis');
        [$starter] = $this->tenantWithOwner('Kos Starter', 'starter');

        $this->artisan('invoices:generate')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertFalse($free->plan->hasFeature('billing.auto'));
        $this->assertTrue($starter->plan->hasFeature('billing.auto'));
    }

    // --- Tenant ditangguhkan ---

    public function test_tenant_ditangguhkan_karena_langganan_membaca_dan_mengekspor_tetapi_tidak_menulis(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('Kos A', 'starter');
        [$other] = $this->tenantWithOwner('Kos B', 'starter');
        app(SuspendTenant::class)->handle($tenant);

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('owner.subscription.index'))->assertOk();
        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($owner)->post(route('owner.subscription.update'), ['plan_key' => 'pro', 'billing_period' => 'monthly'])->assertForbidden();

        $response = $this->actingAs($owner)->get(route('owner.export'))->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        $this->assertArrayHasKey('langganan.csv', $names);
        $this->assertArrayHasKey('tagihan_langganan.csv', $names);
        $this->assertStringContainsString($this->pendingInvoice($tenant)->number, $names['tagihan_langganan.csv']);
        $this->assertStringNotContainsString($this->pendingInvoice($other)->number, $names['tagihan_langganan.csv']);
    }

    public function test_langganan_tenant_lain_tidak_terbaca_lewat_model(): void
    {
        [$a] = $this->tenantWithOwner('Kos A', 'starter');
        $this->tenantWithOwner('Kos B', 'pro');

        $plans = TenantContext::instance()->runAs($a, fn () => Subscription::query()->with('plan')->get()->pluck('plan.key')->unique()->values()->all());

        $this->assertEqualsCanonicalizing(['free', 'starter'], $plans);
    }

    // --- Perbaikan hasil audit tenant-auditor ---

    public function test_tenant_ditutup_tidak_bisa_ditangguhkan_atau_diaktifkan_dari_panel(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $tenant->forceFill(['status' => TenantStatus::Closed])->save();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.tenants.suspend', $tenant))->assertSessionHasErrors('tenant');
        $this->actingAs($admin)->post(route('admin.tenants.activate', $tenant))->assertSessionHasErrors('tenant');

        $this->assertSame(TenantStatus::Closed, $tenant->refresh()->status);
    }

    public function test_tagihan_langganan_lama_tidak_bisa_dibayar_dan_tidak_memundurkan_paket(): void
    {
        [$tenant] = $this->tenantWithOwner('Kos A', 'starter');
        $old = $this->pendingInvoice($tenant);
        // Percobaan berakhir tanpa bayar: tenant kembali ke Gratis dan tagihan lama menjadi void. Paksa kembali pending.
        $this->travelTo(now()->addDays(15));
        app(ProcessSubscriptions::class)->handle($tenant);
        TenantContext::instance()->runAs($tenant, fn () => SubscriptionInvoice::query()->whereKey($old->id)->update(['status' => 'pending']));
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.tenants.invoices.pay', [$tenant, $old->id]), ['payment_method' => 'Transfer'])
            ->assertSessionHasErrors('invoice');

        $this->assertSame('free', $tenant->refresh()->plan->key);
        $this->assertSame('pending', TenantContext::instance()->runAs($tenant, fn () => $old->fresh())->status->value);
    }

    public function test_id_tagihan_terlalu_besar_menghasilkan_404_bukan_500(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.tenants.invoices.pay', [$tenant, '99999999999999999999999']), ['payment_method' => 'Transfer'])
            ->assertNotFound();
    }

    public function test_detail_tenant_hanya_memuat_tagihan_tenant_itu_dan_tanpa_kolom_terlarang(): void
    {
        [$a] = $this->tenantWithOwner('Kos A', 'starter');
        [$b] = $this->tenantWithOwner('Kos B', 'pro');
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get(route('admin.tenants.show', $a))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tenants/Show')
                ->has('invoices', 1)
                ->where('invoices.0.number', $this->pendingInvoice($a)->number)
                ->missing('tenant.settings')
                ->missing('invoices.0.tenant_id'));

        $this->assertStringNotContainsString($this->pendingInvoice($b)->number, $response->getContent());
    }

    public function test_semua_route_admin_menolak_pemilik_dan_staf(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('Kos A', 'starter');
        $staff = User::factory()->forTenant($tenant)->staff(['finance.view'])->create();
        $invoice = $this->pendingInvoice($tenant);
        $pay = route('admin.tenants.invoices.pay', [$tenant, $invoice->id]);

        foreach ([$owner, $staff] as $user) {
            $this->actingAs($user)->get(route('admin.tenants.show', $tenant))->assertForbidden();
            $this->actingAs($user)->post(route('admin.tenants.activate', $tenant))->assertForbidden();
            $this->actingAs($user)->post($pay, ['payment_method' => 'Transfer'])->assertForbidden();
        }

        $this->assertSame('pending', TenantContext::instance()->runAs($tenant, fn () => $invoice->fresh())->status->value);
    }
}
