<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MessagingHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffA;

    private User $staffSettingsA;

    private User $ownerB;

    private Resident $residentA;

    private Resident $residentB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 04:00:00');
        $this->seed(MessageTemplateSeeder::class);

        $this->tenantA = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 300])->id]);
        $this->tenantB = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 300])->id]);
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffA = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->staffSettingsA = User::factory()->forTenant($this->tenantA)->staff(['settings.manage'])->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();

        $this->residentA = $this->inTenant($this->tenantA, fn () => Resident::factory()->create(['name' => 'Budi', 'phone' => '+6281234567890']));
        $this->residentB = $this->inTenant($this->tenantB, fn () => Resident::factory()->create(['name' => 'Siti', 'phone' => '+6289876543210']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function logIn(Tenant $tenant, Resident $resident, array $attributes = []): MessageLog
    {
        return $this->inTenant($tenant, fn () => MessageLog::factory()->create($attributes + [
            'resident_id' => $resident->id, 'recipient' => $resident->phone, 'body' => 'Isi rahasia '.$resident->name,
        ]));
    }

    public function test_halaman_menampilkan_kuota_template_dan_log(): void
    {
        $this->inTenant($this->tenantA, fn () => UsageCounter::factory()->create(['used' => 120]));
        $this->logIn($this->tenantA, $this->residentA, ['status' => MessageStatus::Failed, 'error_code' => 'TIMEOUT']);

        $this->actingAs($this->ownerA)->get('/app/pesan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Messages/Index')
                ->where('quota', ['period' => '2026-11', 'limit' => 300, 'used' => 120, 'remaining' => 180])
                ->where('provider', 'fake')
                ->has('templates', 7)
                ->where('templates.0.is_custom', false)
                ->where('variables', ['nama', 'jumlah', 'jatuh_tempo', 'link'])
                ->has('logs', 1)
                ->where('logs.0.resident_name', 'Budi')
                ->where('logs.0.recipient', '+6281*****7890')
                ->where('logs.0.error_code', 'TIMEOUT')
                ->where('logs.0.can_resend', true)
            );
    }

    public function test_props_tidak_memuat_nomor_utuh_isi_pesan_atau_kolom_terlarang(): void
    {
        $this->logIn($this->tenantA, $this->residentA);

        $json = json_encode($this->actingAs($this->ownerA)->get('/app/pesan')->viewData('page')['props']);

        foreach (['tenant_id', 'provider_message_id', 'token_hash', 'password', 'Isi rahasia', '+6281234567890'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, "Props memuat $forbidden");
        }
    }

    public function test_staf_tanpa_izin_pengaturan_ditolak_dan_staf_dengan_izin_diterima(): void
    {
        $log = $this->logIn($this->tenantA, $this->residentA, ['status' => MessageStatus::Failed]);

        $this->get('/app/pesan')->assertRedirect('/masuk');
        $this->actingAs($this->staffA)->get('/app/pesan')->assertForbidden();
        $this->actingAs($this->staffA)->patch('/app/pesan/templat/receipt', ['body' => 'Halo {{nama}}'])->assertForbidden();
        $this->actingAs($this->staffA)->post("/app/pesan/{$log->id}/kirim-ulang")->assertForbidden();
        $this->actingAs($this->staffSettingsA)->get('/app/pesan')->assertOk();
    }

    public function test_pemilik_menyunting_template_dan_category_tetap_utility(): void
    {
        $this->actingAs($this->ownerA)->patch('/app/pesan/templat/receipt', [
            'body' => 'Terima kasih {{nama}}, {{jumlah}} sudah masuk.',
            'category' => 'marketing',
            'tenant_id' => $this->tenantB->id,
        ])->assertSessionHasNoErrors();

        $custom = $this->inTenant($this->tenantA, fn () => MessageTemplate::query()->sole());
        $this->assertSame('utility', $custom->category);
        $this->assertSame($this->tenantA->id, $custom->tenant_id);
        $this->assertSame('receipt', $custom->key);

        $this->actingAs($this->ownerA)->get('/app/pesan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('templates', fn ($templates) => collect($templates)->firstWhere('key', 'receipt')['is_custom'] === true
                    && str_starts_with(collect($templates)->firstWhere('key', 'receipt')['body'], 'Terima kasih'))
            );

        $this->actingAs($this->ownerA)->post('/app/pesan/templat/receipt/reset')->assertSessionHasNoErrors();
        $this->actingAs($this->ownerA)->get('/app/pesan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('templates', fn ($templates) => collect($templates)->firstWhere('key', 'receipt')['is_custom'] === false));
    }

    public function test_template_dengan_variabel_tak_dikenal_atau_kosong_ditolak_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->ownerA)->patch('/app/pesan/templat/receipt', ['body' => 'Halo {{alamat}}'])
            ->assertSessionHasErrors(['body' => 'Variabel tidak dikenal: {{alamat}}. Yang diizinkan: {{nama}}, {{jumlah}}, {{jatuh_tempo}}, {{link}}.']);
        $this->actingAs($this->ownerA)->patch('/app/pesan/templat/receipt', ['body' => ''])
            ->assertSessionHasErrors(['body' => 'Isi pesan tidak boleh kosong.']);
        $this->actingAs($this->ownerA)->patch('/app/pesan/templat/tidak-ada', ['body' => 'x'])->assertNotFound();

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => MessageTemplate::query()->count()));
    }

    public function test_pesan_gagal_dikirim_ulang_dari_log_dan_pesan_lain_ditolak(): void
    {
        $failed = $this->logIn($this->tenantA, $this->residentA, ['status' => MessageStatus::Failed, 'attempts' => 3, 'error_code' => 'TIMEOUT']);
        $sent = $this->logIn($this->tenantA, $this->residentA, ['status' => MessageStatus::Sent]);
        $fake = app(WhatsAppProvider::class);
        $this->assertInstanceOf(FakeWhatsAppProvider::class, $fake);

        $this->actingAs($this->ownerA)->post("/app/pesan/{$failed->id}/kirim-ulang")->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->actingAs($this->ownerA)->post("/app/pesan/{$sent->id}/kirim-ulang")->assertSessionHasErrors('message');

        $fresh = $this->inTenant($this->tenantA, fn () => $failed->fresh());
        $this->assertSame(MessageStatus::Sent, $fresh->status);
        $this->assertCount(1, $fake->sent());
        $this->assertSame(1, $this->inTenant($this->tenantA, fn () => UsageCounter::query()->value('used')));
    }

    public function test_tidak_ada_route_hapus_log_atau_template(): void
    {
        $log = $this->logIn($this->tenantA, $this->residentA);

        $deleteRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'app/pesan') && in_array('DELETE', $route->methods(), true));
        $this->assertCount(0, $deleteRoutes);

        $this->actingAs($this->ownerA)->delete("/app/pesan/{$log->id}")->assertStatus(404);
        $this->actingAs($this->ownerA)->delete('/app/pesan/templat/receipt')->assertStatus(405);
        $this->assertSame(1, $this->inTenant($this->tenantA, fn () => MessageLog::query()->count()));
    }

    public function test_pengguna_tenant_a_tidak_bisa_membaca_atau_mengirim_ulang_pesan_tenant_b(): void
    {
        Queue::fake();
        $theirs = $this->logIn($this->tenantB, $this->residentB, ['status' => MessageStatus::Failed, 'body' => 'Rahasia B']);
        $this->inTenant($this->tenantB, fn () => MessageTemplate::factory()->create(['key' => 'receipt', 'body' => 'Templat rahasia B {{nama}}']));
        $this->inTenant($this->tenantB, fn () => UsageCounter::factory()->create(['used' => 77]));

        $this->actingAs($this->ownerA)->post("/app/pesan/{$theirs->id}/kirim-ulang")->assertNotFound();
        $this->assertSame(MessageStatus::Failed, $this->inTenant($this->tenantB, fn () => $theirs->fresh())->status);

        $json = json_encode($this->actingAs($this->ownerA)->get('/app/pesan')->viewData('page')['props']);
        foreach (['Rahasia B', 'Templat rahasia B', 'Siti', '+6289876543210'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->actingAs($this->ownerA)->get('/app/pesan')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('quota.used', 0)->has('logs', 0));
    }

    public function test_filter_status_log(): void
    {
        $this->logIn($this->tenantA, $this->residentA, ['status' => MessageStatus::Failed]);
        $this->logIn($this->tenantA, $this->residentA, ['status' => MessageStatus::Sent]);

        $this->actingAs($this->ownerA)->get('/app/pesan?status=failed')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('logs', 1)->where('logs.0.status', 'failed'));
        $this->actingAs($this->ownerA)->get('/app/pesan?status=bukan')->assertSessionHasErrors('status');
    }
}
