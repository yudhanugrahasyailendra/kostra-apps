<?php

namespace Tests\Feature\Subscriptions;

use App\Actions\ActivateTenant;
use App\Actions\ChangeSubscriptionPlan;
use App\Actions\ProcessSubscriptions;
use App\Actions\RecordSubscriptionPayment;
use App\Actions\RegisterOwner;
use App\Actions\SuspendTenant;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanLimitExceededException;
use App\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        // Fase 11 memakai paket sungguhan; tanggal dibekukan supaya hitungan hari deterministik.
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(9, 0));
    }

    private function plan(string $key): Plan
    {
        return Plan::query()->where('key', $key)->firstOrFail();
    }

    private function register(string $name = 'Kos Uji'): Tenant
    {
        $user = app(RegisterOwner::class)->handle([
            'kos_name' => $name, 'name' => 'Pemilik', 'email' => fake()->unique()->safeEmail(), 'password' => 'rahasia-banget',
        ]);

        return Tenant::query()->findOrFail($user->tenant_id);
    }

    private function in(Tenant $tenant, callable $fn): mixed
    {
        return TenantContext::instance()->runAs($tenant, $fn);
    }

    private function change(Tenant $tenant, string $key, SubscriptionPeriod $period = SubscriptionPeriod::Monthly): Subscription
    {
        return app(ChangeSubscriptionPlan::class)->handle($tenant, $this->plan($key), $period);
    }

    private function process(Tenant $tenant): Tenant
    {
        app(ProcessSubscriptions::class)->handle($tenant);

        return $tenant->refresh();
    }

    private function pay(Tenant $tenant, SubscriptionInvoice $invoice): void
    {
        app(RecordSubscriptionPayment::class)->handle($tenant, $invoice->id, ['payment_method' => 'Transfer BCA', 'reference' => 'TRX-1']);
    }

    private function pendingInvoice(Tenant $tenant): SubscriptionInvoice
    {
        return $this->in($tenant, fn () => SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::Pending->value)->latest('id')->firstOrFail());
    }

    public function test_pendaftaran_membuat_langganan_gratis_dan_menyamakan_plan_id(): void
    {
        $tenant = $this->register();
        $subscription = $tenant->currentSubscription();

        $this->assertSame('free', $subscription->plan->key);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($subscription->plan_id, $tenant->plan_id);
    }

    public function test_paket_gratis_menegakkan_batas_1_properti_dan_10_kamar(): void
    {
        $tenant = $this->register();
        $owner = User::factory()->forTenant($tenant)->owner()->create();

        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Griya A'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Griya B'])
            ->assertSessionHasErrors('limit');

        $property = $this->in($tenant, fn () => Property::query()->firstOrFail());
        $this->actingAs($owner)->post(route('owner.rooms.bulk'), ['property_id' => $property->id, 'start' => 101, 'end' => 110, 'floor' => 1, 'price' => 1000000])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.rooms.bulk'), ['property_id' => $property->id, 'start' => 201, 'end' => 201, 'floor' => 2, 'price' => 1000000])
            ->assertSessionHasErrors('limit');

        $this->assertSame(1, $this->in($tenant, fn () => Property::query()->count()));
        $this->assertSame(10, $this->in($tenant, fn () => Room::query()->count()));
    }

    public function test_percobaan_14_hari_membuat_langganan_trialing_dan_tagihan_pertama(): void
    {
        $tenant = $this->register();
        $subscription = $this->change($tenant, 'starter');
        $tenant->refresh();

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame(TenantStatus::Trial, $tenant->status);
        $this->assertSame('starter', $tenant->plan->key);
        $this->assertTrue($subscription->trial_ends_at->equalTo(now()->addDays(14)));

        $invoice = $this->pendingInvoice($tenant);
        $this->assertSame(49000, $invoice->amount);
        $this->assertSame('2026-11-24', $invoice->period_start->toDateString());
        $this->assertMatchesRegularExpression('/^KST-2026-11-\d{6}$/', $invoice->number);
    }

    public function test_percobaan_hanya_sekali_per_tenant(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $this->travelTo(now()->addDays(15));
        $this->process($tenant);
        $this->assertSame('free', $tenant->refresh()->plan->key);

        // Percobaan sudah dipakai: pindah ke paket berbayar langsung berlangganan (aktif, tagihan menunggu bayar).
        $subscription = $this->change($tenant, 'starter');

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertCount(1, $this->in($tenant, fn () => Subscription::query()->whereNotNull('trial_ends_at')->get()));
    }

    public function test_tanpa_pembayaran_setelah_14_hari_kembali_ke_gratis_dan_tidak_ditangguhkan(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');

        $this->travelTo(now()->addDays(13));
        $this->assertSame('starter', $this->process($tenant)->plan->key);

        $this->travelTo(now()->addDays(1)->addMinute());
        $tenant = $this->process($tenant);
        $this->assertSame('free', $tenant->plan->key);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('free', $tenant->currentSubscription()->plan->key);
        $this->assertSame(SubscriptionInvoiceStatus::Void, $this->in($tenant, fn () => SubscriptionInvoice::query()->firstOrFail())->status);

        // Tidak ada penangguhan walau lewat masa tenggang: tenant sudah di Gratis.
        $this->travelTo(now()->addDays(30));
        $this->assertSame(TenantStatus::Active, $this->process($tenant)->status);
    }

    public function test_dibayar_saat_percobaan_menjadi_aktif_di_akhir_percobaan(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $this->pay($tenant, $this->pendingInvoice($tenant));

        $this->assertSame(SubscriptionStatus::Trialing, $tenant->currentSubscription()->status);

        $this->travelTo(now()->addDays(14)->addMinute());
        $tenant = $this->process($tenant);
        $subscription = $tenant->currentSubscription();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('starter', $tenant->plan->key);
        $this->assertSame('2026-11-24', $subscription->current_period_start->toDateString());
        $this->assertSame('2026-12-23', $subscription->current_period_end->toDateString());
    }

    public function test_tunggakan_menunggak_lalu_ditangguhkan_setelah_masa_tenggang_7_hari(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $this->pay($tenant, $this->pendingInvoice($tenant));
        $this->travelTo(now()->addDays(14)->addMinute());
        $this->process($tenant); // aktif, periode 24 Nov - 23 Des

        // 7 hari sebelum periode habis: tagihan perpanjangan terbit sekali saja.
        $this->travelTo(now()->setDate(2026, 12, 16)->setTime(9, 0));
        $this->process($tenant);
        $this->process($tenant);
        $renewal = $this->pendingInvoice($tenant);
        $this->assertSame('2026-12-24', $renewal->period_start->toDateString());
        $this->assertSame('2026-12-23', $renewal->due_date->toDateString());
        $this->assertSame(1, $this->in($tenant, fn () => SubscriptionInvoice::query()->where('status', 'pending')->count()));

        // Lewat jatuh tempo: menunggak, tenant belum ditangguhkan.
        $this->travelTo(now()->setDate(2026, 12, 25)->setTime(9, 0));
        $tenant = $this->process($tenant);
        $subscription = $tenant->currentSubscription();
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertTrue($subscription->grace_ends_at->equalTo(now()->addDays(7)));
        $this->assertSame(TenantStatus::Active, $tenant->status);

        // Hari ke-6 masa tenggang: masih belum.
        $this->travelTo(now()->addDays(6));
        $this->assertSame(TenantStatus::Active, $this->process($tenant)->status);

        // Setelah 7 hari: ditangguhkan.
        $this->travelTo(now()->addDay());
        $tenant = $this->process($tenant);
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertNotNull($tenant->suspended_at);
    }

    public function test_pembayaran_menghidupkan_lagi_tenant_yang_ditangguhkan_karena_tunggakan(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $this->pay($tenant, $this->pendingInvoice($tenant));
        $this->travelTo(now()->addDays(14)->addMinute());
        $this->process($tenant);
        $this->travelTo(now()->setDate(2026, 12, 16)->setTime(9, 0));
        $this->process($tenant);
        $this->travelTo(now()->setDate(2026, 12, 25)->setTime(9, 0));
        $this->process($tenant);
        $this->travelTo(now()->addDays(7));
        $tenant = $this->process($tenant);
        $this->assertSame(TenantStatus::Suspended, $tenant->status);

        $this->pay($tenant, $this->pendingInvoice($tenant));
        $tenant->refresh();

        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertNull($tenant->suspended_at);
        $this->assertSame(SubscriptionStatus::Active, $tenant->currentSubscription()->status);
        $this->assertSame('2027-01-23', $tenant->currentSubscription()->current_period_end->toDateString());
    }

    public function test_tangguhan_manual_dan_aktifkan_kembali(): void
    {
        $tenant = $this->register();

        app(SuspendTenant::class)->handle($tenant);
        $this->assertTrue($tenant->refresh()->isReadOnly());

        app(ActivateTenant::class)->handle($tenant);
        $this->assertSame(TenantStatus::Active, $tenant->refresh()->status);
        $this->assertNull($tenant->suspended_at);
    }

    public function test_turun_paket_ditolak_bila_kamar_melebihi_batas_tujuan(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'pro');
        $this->in($tenant, function () {
            $property = Property::factory()->create();
            Room::factory()->count(12)->state(['property_id' => $property->id])->sequence(fn ($s) => ['number' => (string) (100 + $s->index)])->create();
        });

        try {
            $this->change($tenant, 'free');
            $this->fail('Turun ke Gratis seharusnya ditolak.');
        } catch (PlanLimitExceededException $e) {
            $this->assertStringContainsString('10 kamar', $e->getMessage());
        }

        $this->assertSame('pro', $tenant->refresh()->plan->key);
        $this->assertSame(12, $this->in($tenant, fn () => Room::query()->count()));
    }

    public function test_turun_paket_yang_muat_berhasil_dan_tagihan_belum_bayar_dibatalkan(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'pro');
        $this->assertSame(129000, $this->pendingInvoice($tenant)->amount);

        $this->change($tenant, 'free');

        $tenant->refresh();
        $this->assertSame('free', $tenant->plan->key);
        $this->assertSame('free', $tenant->currentSubscription()->plan->key);
        $this->assertSame(0, $this->in($tenant, fn () => SubscriptionInvoice::query()->where('status', 'pending')->count()));
    }

    public function test_naik_paket_langsung_membuka_batas_dan_fitur_baru(): void
    {
        $tenant = $this->register();
        $owner = User::factory()->forTenant($tenant)->owner()->create();
        $this->assertFalse($tenant->plan->hasFeature('billing.auto'));
        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Griya A']);
        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Griya B'])->assertSessionHasErrors('limit');

        $this->actingAs($owner)->post(route('owner.subscription.update'), ['plan_key' => 'pro', 'billing_period' => 'monthly'])
            ->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertSame('pro', $tenant->plan->key);
        $this->assertTrue($tenant->plan->hasFeature('billing.auto'));
        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Griya B'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->in($tenant, fn () => Property::query()->count()));
    }

    public function test_ganti_ke_tahunan_memakai_harga_tahunan(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter', SubscriptionPeriod::Yearly);

        $invoice = $this->pendingInvoice($tenant);
        $this->assertSame(490000, $invoice->amount);
        $this->assertSame('2027-11-23', $invoice->period_end->toDateString());
    }

    public function test_ganti_paket_ke_paket_yang_sama_ditolak(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');

        $this->expectException(ValidationException::class);
        $this->change($tenant, 'starter');
    }

    public function test_ganti_paket_saat_percobaan_menerbitkan_ulang_tagihan_dengan_harga_baru(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $this->change($tenant, 'pro');

        $this->assertSame(129000, $this->pendingInvoice($tenant)->amount);
        $this->assertSame(1, $this->in($tenant, fn () => SubscriptionInvoice::query()->where('status', 'void')->count()));
        $this->assertSame('pro', $tenant->refresh()->plan->key);
    }

    public function test_penjadwal_idempoten(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $this->travelTo(now()->addDays(15));

        $this->process($tenant);
        $this->process($tenant);

        $this->assertSame(1, $this->in($tenant, fn () => Subscription::query()->where('status', 'active')->count()));
        $this->assertSame(1, $this->in($tenant, fn () => SubscriptionInvoice::query()->count()));
    }

    public function test_nomor_tagihan_langganan_unik_lintas_tenant(): void
    {
        $a = $this->register('Kos A');
        $b = $this->register('Kos B');
        $this->change($a, 'starter');
        $this->change($b, 'starter');

        $numbers = [$this->pendingInvoice($a)->number, $this->pendingInvoice($b)->number];

        $this->assertCount(2, array_unique($numbers));
        // Unik di database lintas tenant (kolom number unique): dua baris, dua nomor.
        $this->assertSame(2, TenantContext::instance()->unscoped(fn () => SubscriptionInvoice::query()->distinct()->count('number')));
    }

    public function test_pembayaran_tagihan_yang_sudah_lunas_ditolak(): void
    {
        $tenant = $this->register();
        $this->change($tenant, 'starter');
        $invoice = $this->pendingInvoice($tenant);
        $this->pay($tenant, $invoice);

        $this->expectException(ValidationException::class);
        $this->pay($tenant, $invoice);
    }
}
