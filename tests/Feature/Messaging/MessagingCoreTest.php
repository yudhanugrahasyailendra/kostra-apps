<?php

namespace Tests\Feature\Messaging;

use App\Contracts\WhatsAppProvider;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Services\Messaging\FakeWhatsAppProvider;
use App\Services\Messaging\MessageTemplateRenderer;
use App\Services\Messaging\SendWindow;
use App\Services\Messaging\StatusUpdate;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Tests\Concerns\MessagingFixtures;
use Tests\TestCase;

class MessagingCoreTest extends TestCase
{
    use MessagingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMessaging();
    }

    // ---- Renderer ----

    public function test_the_renderer_fills_known_variables_with_or_without_spaces(): void
    {
        $renderer = new MessageTemplateRenderer;
        $values = ['nama' => 'Budi', 'jumlah' => 'Rp 1.500.000', 'jatuh_tempo' => '10 Oktober 2026', 'link' => 'https://kostra.test', 'status' => 'Diproses'];

        $this->assertSame(
            'Halo Budi, bayar Rp 1.500.000 sebelum 10 Oktober 2026 di https://kostra.test (Diproses)',
            $renderer->render('Halo {{nama}}, bayar {{ jumlah }} sebelum {{jatuh_tempo}} di {{ link }} ({{status}})', $values),
        );
    }

    public function test_the_renderer_rejects_unknown_variables_everywhere(): void
    {
        $renderer = new MessageTemplateRenderer;

        $this->assertSame(['password', 'Nama'], $renderer->unknownVariables('Halo {{nama}} {{password}} {{Nama}}'));

        $this->expectException(InvalidArgumentException::class);
        $renderer->render('Halo {{nomor_ktp}}', ['nama' => 'Budi']);
    }

    public function test_validation_rejects_unknown_variables_empty_and_oversized_bodies(): void
    {
        $renderer = new MessageTemplateRenderer;

        foreach (['Halo {{rahasia}}', '', '   ', str_repeat('x', 1001)] as $body) {
            try {
                $renderer->assertValid($body);
                $this->fail('Isi template seharusnya ditolak: '.substr($body, 0, 20));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('body', $e->errors());
            }
        }

        $renderer->assertValid('Halo {{nama}}, tagihan {{jumlah}}');
        $this->addToAssertionCount(1);
    }

    public function test_a_missing_value_for_a_used_variable_is_an_error_not_a_blank(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MessageTemplateRenderer)->render('Halo {{nama}}', []);
    }

    public function test_every_system_template_only_uses_allowed_variables(): void
    {
        $renderer = new MessageTemplateRenderer;

        foreach (MessageTemplateKey::cases() as $key) {
            $this->assertSame([], $renderer->unknownVariables($key->defaultBody()), $key->value);
        }
    }

    // ---- Jam kirim ----

    public function test_the_send_window_defers_messages_outside_the_tenant_hours(): void
    {
        $window = new SendWindow;
        $tenant = $this->tenant; // Asia/Makassar, UTC+8
        $at = fn (string $utc) => CarbonImmutable::parse($utc, 'UTC');
        $next = fn (string $utc, string $start = '08:00:00', string $end = '20:00:00') => $window->nextAllowed($tenant, $start, $end, $at($utc))->format('Y-m-d H:i');

        $this->assertSame('2026-10-15 03:00', $next('2026-10-15 03:00:00'), '11:00 lokal: di dalam jendela');
        $this->assertSame('2026-10-15 00:00', $next('2026-10-14 23:00:00'), '07:00 lokal: ditunda ke 08:00 hari yang sama');
        $this->assertSame('2026-10-15 00:00', $next('2026-10-15 00:00:00'), '08:00 tepat: boleh');
        $this->assertSame('2026-10-15 11:59', $next('2026-10-15 11:59:00'), '19:59 lokal: boleh');
        $this->assertSame('2026-10-16 00:00', $next('2026-10-15 12:00:00'), '20:00 tepat: ditunda ke besok 08:00');
        $this->assertSame('2026-10-16 00:00', $next('2026-10-15 15:30:00'), '23:30 lokal: ditunda ke besok 08:00');
    }

    public function test_a_window_that_crosses_midnight_and_an_unrestricted_window_work(): void
    {
        $window = new SendWindow;
        $at = fn (string $utc) => CarbonImmutable::parse($utc, 'UTC');
        $next = fn (string $utc, string $start, string $end) => $window->nextAllowed($this->tenant, $start, $end, $at($utc))->format('Y-m-d H:i');

        // 22:00 sampai 06:00 lokal (14:00 sampai 22:00 UTC).
        $this->assertSame('2026-10-15 15:00', $next('2026-10-15 15:00:00', '22:00:00', '06:00:00'), '23:00 lokal di dalam jendela');
        $this->assertSame('2026-10-15 20:00', $next('2026-10-15 20:00:00', '22:00:00', '06:00:00'), '04:00 lokal di dalam jendela');
        $this->assertSame('2026-10-15 14:00', $next('2026-10-15 04:00:00', '22:00:00', '06:00:00'), '12:00 lokal ditunda ke 22:00');
        $this->assertSame('2026-10-15 03:00', $next('2026-10-15 03:00:00', '00:00:00', '00:00:00'), 'mulai = selesai berarti tanpa batas');
    }

    // ---- Template ----

    public function test_the_seeder_creates_the_system_templates_once(): void
    {
        $this->seed(MessageTemplateSeeder::class);
        $this->seed(MessageTemplateSeeder::class);

        $system = $this->withoutTenant(fn () => MessageTemplate::query()->whereNull('tenant_id')->orderBy('key')->get());

        $this->assertSame(count(MessageTemplateKey::cases()), $system->count());
        $this->assertEqualsCanonicalizing(array_map(fn ($k) => $k->value, MessageTemplateKey::cases()), $system->pluck('key')->all());
        $this->assertSame(['utility'], $system->pluck('category')->unique()->values()->all());
        $this->assertSame([true], $system->pluck('is_active')->unique()->values()->all());
    }

    public function test_a_tenant_sees_the_system_templates_and_never_another_tenants_copy(): void
    {
        [$other, $otherOwner] = $this->tenantWithOwner('business');
        $this->actingAs($otherOwner)->put(route('owner.message-templates.update', 'receipt'), ['name' => 'Kuitansi tetangga', 'body' => 'Rahasia tetangga {{nama}}', 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $mine = $this->asTenant($this->tenant, fn () => MessageTemplate::query()->pluck('body', 'key')->all());
        $this->assertStringNotContainsString('Rahasia tetangga', implode(' ', $mine));
        $this->assertCount(count(MessageTemplateKey::cases()), $mine);

        $theirs = $this->asTenant($other, fn () => MessageTemplate::resolve(MessageTemplateKey::Receipt));
        $this->assertSame('Rahasia tetangga {{nama}}', $theirs->body);
        $this->assertSame($other->id, $theirs->tenant_id);
    }

    public function test_editing_a_template_creates_a_tenant_copy_and_leaves_the_system_row_alone(): void
    {
        $original = $this->withoutTenant(fn () => MessageTemplate::query()->whereNull('tenant_id')->forKey(MessageTemplateKey::Receipt)->first());

        $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'receipt'), ['name' => 'Kuitansi kami', 'body' => 'Terima kasih {{nama}}, {{jumlah}} diterima.', 'is_active' => 1])
            ->assertRedirect(route('owner.message-templates.index'));

        $system = $this->withoutTenant(fn () => MessageTemplate::query()->whereNull('tenant_id')->forKey(MessageTemplateKey::Receipt)->first());
        $this->assertSame($original->body, $system->body);

        $copy = $this->asTenant($this->tenant, fn () => MessageTemplate::resolve(MessageTemplateKey::Receipt));
        $this->assertSame([$this->tenant->id, 'Kuitansi kami', 'Terima kasih {{nama}}, {{jumlah}} diterima.', 'utility'], [$copy->tenant_id, $copy->name, $copy->body, $copy->category]);

        // Mengedit lagi memperbarui salinan yang sama, bukan menambah baris.
        $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'receipt'), ['name' => 'Kuitansi kami', 'body' => 'Versi dua {{nama}}', 'is_active' => 1]);
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => MessageTemplate::query()->where('tenant_id', $this->tenant->id)->forKey(MessageTemplateKey::Receipt)->count()));
    }

    public function test_the_category_can_never_be_anything_but_utility(): void
    {
        $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'receipt'), [
            'name' => 'Promo', 'body' => 'Diskon besar {{nama}}', 'is_active' => 1, 'category' => 'marketing',
        ])->assertSessionHasNoErrors();

        $copy = $this->asTenant($this->tenant, fn () => MessageTemplate::resolve(MessageTemplateKey::Receipt));
        $this->assertSame('utility', $copy->category);

        // Juga bila model diubah langsung.
        $this->asTenant($this->tenant, fn () => $copy->update(['category' => 'marketing']));
        $this->assertSame('utility', $this->asTenant($this->tenant, fn () => MessageTemplate::resolve(MessageTemplateKey::Receipt))->category);

        $created = $this->asTenant($this->tenant, fn () => MessageTemplate::create(['key' => 'lease_expiring', 'channel' => 'whatsapp', 'name' => 'X', 'body' => 'Halo {{nama}}', 'category' => 'marketing']));
        $this->assertSame('utility', $created->category);
    }

    public function test_template_edits_reject_unknown_variables_and_bad_input(): void
    {
        $put = fn (array $data) => $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'receipt'), $data + ['name' => 'Kuitansi', 'is_active' => 1]);

        $put(['body' => 'Halo {{nomor_ktp}}'])->assertSessionHasErrors('body');
        $put(['body' => ''])->assertSessionHasErrors('body');
        $put(['body' => str_repeat('a', 1001)])->assertSessionHasErrors('body');
        $put(['body' => 'Halo {{nama}}', 'name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'tidak-ada'), ['name' => 'X', 'body' => 'Halo'])->assertNotFound();

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => MessageTemplate::query()->where('tenant_id', $this->tenant->id)->count()));
    }

    // ---- Penyedia ----

    public function test_the_provider_is_chosen_by_configuration_and_defaults_to_the_fake(): void
    {
        $this->assertInstanceOf(FakeWhatsAppProvider::class, app(WhatsAppProvider::class));
        $this->assertSame('fake', config('kostra.whatsapp.provider'));
    }

    public function test_an_unknown_provider_fails_loudly_instead_of_sending(): void
    {
        config(['kostra.whatsapp.provider' => 'penyedia-nyata']);
        app()->forgetInstance(WhatsAppProvider::class);

        $this->expectException(LogicException::class);
        app(WhatsAppProvider::class);
    }

    public function test_the_fake_provider_reads_status_webhooks(): void
    {
        $provider = new FakeWhatsAppProvider;

        $delivered = $provider->parseStatusWebhook(['id' => 'abc', 'status' => 'delivered']);
        $this->assertSame(['abc', StatusUpdate::DELIVERED], [$delivered->providerMessageId, $delivered->status]);

        $failed = $provider->parseStatusWebhook(['id' => 'abc', 'status' => 'failed', 'error_code' => '131026']);
        $this->assertSame(['failed', '131026'], [$failed->status, $failed->errorCode]);

        $this->assertNull($provider->parseStatusWebhook(['id' => 'abc', 'status' => 'read']));
        $this->assertNull($provider->parseStatusWebhook(['status' => 'delivered']));
        $this->assertNull($provider->parseStatusWebhook([]));
    }
}
