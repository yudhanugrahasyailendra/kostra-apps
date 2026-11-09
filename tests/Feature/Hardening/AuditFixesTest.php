<?php

namespace Tests\Feature\Hardening;

use App\Actions\DeleteResident;
use App\Actions\DeliverWhatsAppMessage;
use App\Actions\MarkOverdueInvoices;
use App\Actions\QueueWhatsAppMessage;
use App\Actions\ResendWhatsAppMessage;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\AuditLog;
use App\Models\MessageLog;
use App\Models\Plan;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Perbaikan temuan audit tenant Fase 12 (docs/audit-fase-12.md). */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Resident $resident;

    private FakeWhatsAppProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 04:00:00');
        $this->seed(MessageTemplateSeeder::class);

        $this->tenant = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 5])->id]);
        $this->resident = $this->inTenant(fn () => Resident::factory()->create());
        $this->fake = app(WhatsAppProvider::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(callable $callback): mixed
    {
        return TenantContext::instance()->runAs($this->tenant, $callback);
    }

    public function test_pesan_gagal_menyamarkan_token_portal_dan_kirim_ulang_menerbitkan_token_baru(): void
    {
        Queue::fake();
        $this->fake->failNext(1, 'INVALID_NUMBER', retryable: false);

        $log = $this->inTenant(fn () => app(QueueWhatsAppMessage::class)->handle(MessageTemplateKey::PortalLink, $this->resident, []));
        $this->assertMatchesRegularExpression('#/p/[a-f0-9]{64}#', $log->body);

        $this->inTenant(fn () => app(DeliverWhatsAppMessage::class)->handle($log));

        $failed = $this->inTenant(fn () => $log->fresh());
        $this->assertSame(MessageStatus::Failed, $failed->status);
        $this->assertDoesNotMatchRegularExpression('#/p/[a-f0-9]{64}#', $failed->body);
        $this->assertStringContainsString('/p/[tautan-rahasia]', $failed->body);

        $tokensBefore = $this->inTenant(fn () => PortalToken::query()->count());
        $this->inTenant(fn () => app(ResendWhatsAppMessage::class)->handle($failed));

        $queued = $this->inTenant(fn () => MessageLog::query()->findOrFail($log->id));
        $this->assertMatchesRegularExpression('#/p/[a-f0-9]{64}#', $queued->body);
        $this->assertSame($tokensBefore + 1, $this->inTenant(fn () => PortalToken::query()->count()));
    }

    public function test_menghapus_penghuni_mencabut_tautan_portalnya(): void
    {
        Queue::fake();
        $this->inTenant(fn () => app(QueueWhatsAppMessage::class)->handle(MessageTemplateKey::PortalLink, $this->resident, []));
        $this->assertSame(1, $this->inTenant(fn () => PortalToken::query()->usable()->count()));

        $this->inTenant(fn () => app(DeleteResident::class)->handle($this->resident));

        $this->assertSame(0, $this->inTenant(fn () => PortalToken::query()->usable()->count()));
    }

    public function test_audit_log_tidak_menyimpan_email_dan_telepon_pengguna(): void
    {
        $user = User::factory()->forTenant($this->tenant)->owner()->create(['email' => 'rahasia@contoh.test', 'phone' => '+628123456789']);

        $logs = $this->inTenant(fn () => AuditLog::query()->where('auditable_id', $user->id)->get());

        foreach ($logs as $log) {
            $this->assertStringNotContainsString('rahasia@contoh.test', json_encode($log->new_values));
            $this->assertStringNotContainsString('+628123456789', json_encode($log->new_values));
        }
    }

    public function test_mark_overdue_satu_tenant_gagal_tidak_menghentikan_tenant_lain(): void
    {
        Tenant::factory()->create();

        $this->app->bind(MarkOverdueInvoices::class, fn () => new class extends MarkOverdueInvoices
        {
            public static int $calls = 0;

            public function handle(Tenant $tenant, ?CarbonInterface $today = null): int
            {
                self::$calls++;

                throw new \RuntimeException('gagal sengaja');
            }
        });

        $this->artisan('invoices:mark-overdue')->assertFailed();
        $this->assertSame(2, $this->app->make(MarkOverdueInvoices::class)::$calls);
    }
}
