<?php

namespace Tests\Feature\Subscriptions;

use App\Actions\Subscriptions\SuspendTenant;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SubscriptionFixtures;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase, SubscriptionFixtures;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSubscriptions();
        $this->admin = $this->admin();
    }

    private function fresh(Tenant $tenant): Tenant
    {
        return Tenant::query()->findOrFail($tenant->getKey());
    }

    // ---- Akses ----

    public function test_only_the_super_admin_can_open_the_admin_panel(): void
    {
        [$tenant, $owner] = $this->tenantOn('pro');
        $staff = User::factory()->for($tenant)->staff()->create();
        $invoice = $this->subscribe($this->tenantOn('free')[0], 'pro');

        $urls = [
            ['get', route('admin.dashboard')],
            ['get', route('admin.tenants.index')],
            ['get', route('admin.tenants.show', $tenant)],
            ['get', route('admin.plans.index')],
            ['get', route('admin.plans.edit', $this->plan('pro'))],
            ['post', route('admin.tenants.suspend', $tenant)],
            ['post', route('admin.tenants.activate', $tenant)],
            ['post', route('admin.tenants.plan', $tenant)],
            ['post', route('admin.subscription-invoices.pay', $invoice)],
            ['post', route('admin.subscription-invoices.void', $invoice)],
            ['put', route('admin.plans.update', $this->plan('pro'))],
        ];

        foreach ([$owner, $staff] as $user) {
            foreach ($urls as [$method, $url]) {
                $status = $this->actingAs($user)->{$method}($url)->getStatusCode();

                // Tagihan tenant lain tidak ditemukan oleh binding bertenant (404) sebelum peran diperiksa: tetap tertutup.
                $this->assertContains($status, [403, 404], "{$method} {$url} terbuka untuk non-admin ({$status}).");
            }
        }

        foreach ($urls as [$method, $url]) {
            auth()->logout();
            $this->{$method}($url)->assertRedirect();
        }

        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_nothing_in_the_owner_panel_is_reachable_from_the_admin_role_by_accident(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tenants.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('owner.subscription.show'))->assertForbidden();
    }

    // ---- Ringkasan ----

    public function test_the_dashboard_counts_tenants_subscriptions_and_unpaid_invoices(): void
    {
        $this->tenantOn('free');
        [$paying] = $this->tenantOn('free');
        $this->pay($this->subscribe($paying, 'pro'));
        [$unpaid] = $this->tenantOn('free');
        $this->subscribe($unpaid, 'starter');
        [$suspended] = $this->tenantOn('pro');
        app(SuspendTenant::class)($suspended);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-stat="active">3</div>', false)
            ->assertSee('data-stat="paying">2</div>', false)
            ->assertSee('data-stat="pending">1</div>', false)
            ->assertSee('Rp 79.000');
    }

    // ---- Daftar tenant dan pencarian ----

    public function test_one_search_box_finds_a_tenant_by_name_slug_or_owner_details(): void
    {
        [$melati, $melatiOwner] = $this->tenantWithOwner('pro');
        $melati->update(['name' => 'Kos Melati Indah', 'slug' => 'melati-xyz']);
        $melatiOwner->update(['name' => 'Siti Rahayu', 'email' => 'siti@contoh.test', 'phone' => '+628111222333']);
        [$other] = $this->tenantWithOwner('pro');
        $other->update(['name' => 'Kos Anggrek']);

        foreach (['Melati', 'melati-xyz', 'Rahayu', 'siti@contoh', '+628111222'] as $term) {
            $this->actingAs($this->admin)->get(route('admin.tenants.index', ['q' => $term]))->assertOk()
                ->assertSee('Kos Melati Indah')->assertDontSee('Kos Anggrek');
        }

        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['q' => 'tidak-ada-ini']))->assertSee('Tidak ada tenant yang cocok');
    }

    public function test_search_only_matches_owners_not_staff(): void
    {
        [$tenant] = $this->tenantWithOwner('pro');
        $tenant->update(['name' => 'Kos Tersembunyi']);
        User::factory()->for($tenant)->staff()->create(['name' => 'Penjaga Rahasia']);

        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['q' => 'Penjaga Rahasia']))->assertDontSee('Kos Tersembunyi');
    }

    public function test_the_list_filters_by_status_and_plan_and_shows_usage(): void
    {
        [$a] = $this->tenantOn('pro');
        $a->update(['name' => 'Kos Pro Aktif']);
        [$b] = $this->tenantOn('free');
        $b->update(['name' => 'Kos Gratis Tangguh']);
        app(SuspendTenant::class)($b);
        $this->addRooms($a, 7);
        $this->asTenant($a, fn () => UsageCounter::create(['metric' => 'whatsapp_messages', 'period' => '2026-10', 'used' => 42]));

        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['status' => 'suspended']))->assertSee('Kos Gratis Tangguh')->assertDontSee('Kos Pro Aktif');
        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['plan_id' => $this->plan('pro')->id]))->assertSee('Kos Pro Aktif')->assertDontSee('Kos Gratis Tangguh');

        $html = $this->actingAs($this->admin)->get(route('admin.tenants.index', ['q' => 'Kos Pro Aktif']))->getContent();
        $this->assertMatchesRegularExpression('/>\s*7\s*<\/td>\s*<td[^>]*>\s*42\s*<\/td>/', $html, 'Kamar 7 dan pesan 42 tampil pada baris tenant.');
        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['status' => 'bukan-status']))->assertSessionHasErrors('status');
    }

    public function test_the_list_uses_a_bounded_number_of_queries_for_many_tenants(): void
    {
        foreach (range(1, 25) as $i) {
            $this->tenantOn('free');
        }

        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.tenants.index'))->assertOk();

        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()), 'Pemakaian per halaman dihitung berkelompok, bukan satu query per tenant.');
    }

    // ---- Detail tenant dan aksi ----

    public function test_the_tenant_page_shows_usage_owner_and_invoices(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->addRooms($tenant, 4);
        $invoice = $this->subscribe($tenant, 'pro');

        $this->actingAs($this->admin)->get(route('admin.tenants.show', $tenant))->assertOk()
            ->assertSee($tenant->name)->assertSee($owner->name)->assertSee($invoice->number)
            ->assertSee('Rp 199.000')->assertSee('4 / 100')->assertSee('Catat pembayaran');
    }

    public function test_suspending_and_reactivating_through_the_ui_is_audited_with_the_admin(): void
    {
        [$tenant, $owner] = $this->tenantOn('pro');

        $this->actingAs($this->admin)->post(route('admin.tenants.suspend', $tenant))->assertRedirect(route('admin.tenants.show', $tenant));
        $this->assertSame(TenantStatus::Suspended, $this->fresh($tenant)->status);

        // Pemilik hanya bisa membaca.
        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'baru@example.com'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.tenants.activate', $tenant))->assertRedirect();
        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);

        // Dua perubahan status terakhir (tangguhkan, aktifkan) dilakukan Super Admin lewat panel.
        $log = $this->withoutTenant(fn () => AuditLog::query()->where('auditable_type', 'tenant')->where('auditable_id', $tenant->id)->where('event', 'updated')->latest('id')->limit(2)->get());
        $this->assertCount(2, $log);
        $this->assertSame([$this->admin->id], $log->pluck('user_id')->unique()->values()->all());
        $this->assertSame(['active', 'suspended'], $log->pluck('new_values.status')->all());
    }

    public function test_recording_a_payment_through_the_ui_activates_the_subscription(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');

        $this->actingAs($this->admin)->post(route('admin.subscription-invoices.pay', $invoice), [
            'payment_method' => 'Transfer BCA', 'reference' => 'TRX-889', 'paid_on' => '2026-10-14',
        ])->assertRedirect(route('admin.tenants.show', $tenant))->assertSessionHasNoErrors();

        $paid = $this->invoicesOf($tenant)->sole();
        $this->assertSame([SubscriptionInvoiceStatus::Paid, 'Transfer BCA', 'TRX-889', '2026-10-14'], [$paid->status, $paid->payment_method, $paid->reference, $paid->paid_at->toDateString()]);
        $this->assertSame(SubscriptionStatus::Active, $this->currentSubscription($tenant)->status);
    }

    public function test_payment_form_validation(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');
        $post = fn (array $data) => $this->actingAs($this->admin)->post(route('admin.subscription-invoices.pay', $invoice), $data);

        $post(['payment_method' => ''])->assertSessionHasErrors('payment_method');
        $post(['payment_method' => 'Transfer', 'paid_on' => '2026-10-16'])->assertSessionHasErrors('paid_on');
        $post(['payment_method' => 'Transfer', 'paid_on' => 'kemarin'])->assertSessionHasErrors('paid_on');

        $this->assertSame(SubscriptionInvoiceStatus::Pending, $this->invoicesOf($tenant)->sole()->status);
    }

    public function test_paying_twice_through_the_ui_is_refused(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');
        $this->pay($invoice);

        $this->actingAs($this->admin)->post(route('admin.subscription-invoices.pay', $invoice), ['payment_method' => 'Transfer'])
            ->assertSessionHasErrors('general');
    }

    public function test_voiding_an_invoice_through_the_ui(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');

        $this->actingAs($this->admin)->post(route('admin.subscription-invoices.void', $invoice))->assertRedirect(route('admin.tenants.show', $tenant));

        $this->assertSame(SubscriptionInvoiceStatus::Void, $this->invoicesOf($tenant)->sole()->status);
    }

    public function test_setting_a_plan_through_the_ui_shows_the_limit_problem_instead_of_failing(): void
    {
        [$tenant] = $this->tenantOn('pro');
        $this->addRooms($tenant, 40);

        $this->actingAs($this->admin)->post(route('admin.tenants.plan', $tenant), ['plan_id' => $this->plan('starter')->id])
            ->assertSessionHasErrors('plan');
        $this->assertSame('pro', $this->fresh($tenant)->plan->key);

        $this->actingAs($this->admin)->post(route('admin.tenants.plan', $tenant), ['plan_id' => $this->plan('starter')->id, 'ignore_limits' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('starter', $this->fresh($tenant)->plan->key);

        $this->actingAs($this->admin)->post(route('admin.tenants.plan', $tenant), ['plan_id' => 999999])->assertSessionHasErrors('plan_id');
    }

    // ---- Paket ----

    public function test_the_admin_edits_a_plan_and_new_prices_only_affect_future_invoices(): void
    {
        [$tenant] = $this->tenantOn('free');
        $old = $this->subscribe($tenant, 'pro');
        $this->pay($old);

        $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan('pro')), $this->planPayload(['price_monthly' => 250000, 'monthly_message_quota' => 900]))
            ->assertRedirect(route('admin.plans.index'))->assertSessionHasNoErrors();

        $pro = $this->plan('pro')->refresh();
        $this->assertSame([250000, 900], [$pro->price_monthly, $pro->monthly_message_quota]);
        $this->assertSame(199_000, SubscriptionInvoice::withoutGlobalScopes()->findOrFail($old->id)->amount, 'Tagihan yang sudah terbit tidak berubah.');

        $this->at('2026-11-08 03:00:00');
        $this->lifecycle($tenant);
        $this->assertSame(250_000, $this->invoicesOf($tenant)->last()->amount);
    }

    private function at(string $utc): void
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    }

    private function planPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Pro', 'price_monthly' => 199000, 'price_yearly' => 1990000, 'max_properties' => 3, 'max_rooms' => 100,
            'max_users' => 5, 'monthly_message_quota' => 600, 'features' => ['reports.export'], 'is_active' => 1, 'sort_order' => 2,
        ];
    }

    public function test_plan_validation_and_the_protected_free_plan(): void
    {
        $put = fn (string $key, array $data) => $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan($key)), $data);

        $put('pro', $this->planPayload(['price_monthly' => -1]))->assertSessionHasErrors('price_monthly');
        $put('pro', $this->planPayload(['max_rooms' => 0]))->assertSessionHasErrors('max_rooms');
        $put('pro', $this->planPayload(['features' => ['fitur-siluman']]))->assertSessionHasErrors('features.0');
        $put('pro', $this->planPayload(['name' => '']))->assertSessionHasErrors('name');
        $put('free', $this->planPayload(['price_monthly' => 10000, 'is_active' => 1]))->assertSessionHasErrors('price_monthly');
        $put('free', $this->planPayload(['price_monthly' => 0, 'is_active' => 0]))->assertSessionHasErrors('is_active');

        $this->assertTrue($this->plan('free')->is_active);
        $this->assertSame(0, $this->plan('free')->price_monthly);
    }

    public function test_blank_limits_mean_unlimited(): void
    {
        $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan('pro')), $this->planPayload(['max_properties' => '', 'max_rooms' => '', 'price_yearly' => '']))
            ->assertSessionHasNoErrors();

        $pro = $this->plan('pro')->refresh();
        $this->assertSame([null, null, null], [$pro->max_properties, $pro->max_rooms, $pro->price_yearly]);
    }

    public function test_plan_changes_are_audited_as_platform_events_without_a_tenant(): void
    {
        $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan('pro')), $this->planPayload(['price_monthly' => 210000]));

        $log = $this->withoutTenant(fn () => AuditLog::query()->where('auditable_type', 'plan')->latest('id')->first());
        $this->assertNotNull($log);
        $this->assertNull($log->tenant_id);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(199000, $log->old_values['price_monthly']);
        $this->assertSame(210000, $log->new_values['price_monthly']);
    }

    public function test_the_plan_list_shows_how_many_tenants_use_each_plan(): void
    {
        $this->tenantOn('pro');
        $this->tenantOn('pro');
        $this->tenantOn('free');

        $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertOk()->assertSee('Gratis')->assertSee('Starter')->assertSee('Pro')->assertSee('Business');
        $this->assertSame(2, Plan::query()->withCount('tenants')->where('key', 'pro')->first()->tenants_count);
    }

    public function test_every_current_subscription_row_belongs_to_one_tenant_only(): void
    {
        [$a] = $this->tenantOn('pro');
        [$b] = $this->tenantOn('pro');

        $this->assertSame(1, $this->asTenant($a, fn () => Subscription::query()->current()->count()));
        $this->assertSame(1, $this->asTenant($b, fn () => Subscription::query()->current()->count()));
        $this->assertSame(0, $this->asTenant($a, fn () => Subscription::query()->where('tenant_id', $b->id)->count()));
    }
}
