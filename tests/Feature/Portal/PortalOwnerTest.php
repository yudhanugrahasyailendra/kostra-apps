<?php

namespace Tests\Feature\Portal;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Services\Messaging\MessageTemplateRenderer;
use App\Services\Portal\PortalLinkResolver;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MessagingFixtures;
use Tests\Concerns\PortalFixtures;
use Tests\TestCase;

class PortalOwnerTest extends TestCase
{
    use MessagingFixtures, PortalFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPortal();
        $this->seed(MessageTemplateSeeder::class);
    }

    /**
     * Token asli yang terkirim ke penyedia (satu-satunya tempat token asli boleh ada).
     */
    private function sentTokens(): array
    {
        return collect($this->provider()->sent())
            ->map(fn ($message) => preg_match('#/p/([A-Za-z0-9]{64})#', $message->body, $match) === 1 ? $match[1] : null)
            ->filter()->values()->all();
    }

    private function residentOnly(): Resident
    {
        $lease = $this->leaseFor();

        return $this->asTenant($this->tenant, fn () => Resident::findOrFail($lease->primary_resident_id));
    }

    private function tokens(): Collection
    {
        return $this->asTenant($this->tenant, fn () => PortalToken::query()->orderBy('id')->get());
    }

    // ---- Kirim tautan ----

    public function test_sending_the_link_delivers_a_working_personal_link_over_whatsapp(): void
    {
        $resident = $this->residentOnly();

        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident))
            ->assertRedirect(route('owner.residents.show', $resident))->assertSessionHas('status');

        $raw = $this->sentTokens();
        $this->assertCount(1, $raw);
        $this->assertCount(1, $this->provider()->sent());
        $this->assertStringContainsString($resident->name, $this->provider()->sent()[0]->body);

        auth()->logout();
        $this->get($this->portalUrl($raw[0]))->assertOk()->assertSee($resident->name);
    }

    public function test_the_real_token_is_never_stored_only_its_hash_and_the_log_keeps_a_placeholder(): void
    {
        $resident = $this->residentOnly();
        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident));
        $raw = $this->sentTokens()[0];

        $log = $this->logs()->sole();
        $this->assertStringContainsString(PortalLinkResolver::PLACEHOLDER, $log->body);
        $this->assertStringNotContainsString($raw, $log->body);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9]{64}/', $log->body);

        $token = $this->tokens()->sole();
        $this->assertSame(hash('sha256', $raw), $token->token_hash);
        $this->assertNull($token->created_by, 'Dibuat otomatis saat pengiriman, bukan oleh pengguna tertentu.');

        // Tidak ada tabel yang memuat token asli.
        foreach (['message_logs', 'portal_tokens', 'audit_logs', 'attachments'] as $table) {
            $dump = json_encode($this->withoutTenant(fn () => DB::table($table)->get()));
            $this->assertStringNotContainsString($raw, $dump, "Token asli ditemukan di tabel {$table}.");
        }
    }

    public function test_the_audit_trail_of_a_token_never_carries_the_hash(): void
    {
        $resident = $this->residentOnly();
        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident));
        $hash = $this->tokens()->sole()->token_hash;

        $dump = json_encode($this->withoutTenant(fn () => DB::table('audit_logs')->get()));

        $this->assertStringNotContainsString($hash, $dump);
    }

    public function test_the_message_log_screen_hides_the_personal_link(): void
    {
        $resident = $this->residentOnly();
        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident));
        $raw = $this->sentTokens()[0];

        $this->actingAs($this->owner)->get(route('owner.messages.index'))->assertOk()
            ->assertSee('(tautan portal pribadi)')->assertDontSee($raw)->assertDontSee(PortalLinkResolver::PLACEHOLDER);
    }

    public function test_sending_again_creates_a_new_link_and_keeps_the_old_one_working_until_revoked(): void
    {
        $resident = $this->residentOnly();
        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident));
        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident));
        [$first, $second] = $this->sentTokens();

        $this->assertNotSame($first, $second);
        $this->assertCount(2, $this->tokens());
        auth()->logout();
        $this->get($this->portalUrl($first))->assertOk();
        $this->get($this->portalUrl($second))->assertOk();
    }

    public function test_links_from_automatic_messages_also_open_the_portal_and_are_masked_in_the_log(): void
    {
        $invoice = $this->openInvoice();

        $raw = $this->sentTokens();
        $this->assertCount(1, $raw);
        $this->assertStringNotContainsString($raw[0], $this->logs()->sole()->body);

        $this->get($this->portalUrl($raw[0]))->assertOk()->assertSee($invoice->number);
    }

    public function test_a_resident_who_opted_out_gets_no_link_and_no_token_is_created(): void
    {
        $resident = $this->residentOnly();
        $this->asTenant($this->tenant, fn () => $resident->update(['whatsapp_opt_out_at' => now()]));

        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'berhenti menerima pesan'));

        $log = $this->logs()->sole();
        $this->assertSame([MessageStatus::Skipped, MessageSkipReason::OptedOut], [$log->status, $log->skip_reason]);
        $this->assertSame([], $this->provider()->sent());
        $this->assertCount(0, $this->tokens());
    }

    public function test_a_switched_off_template_sends_nothing_and_says_so(): void
    {
        $resident = $this->residentOnly();
        $this->actingAs($this->owner)->put(route('owner.message-templates.update', MessageTemplateKey::PortalLink->value), [
            'name' => 'Tautan portal penghuni', 'body' => 'Halo {{nama}} {{link}}', 'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('owner.residents.portal.send', $resident))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'dinonaktifkan'));

        $this->assertCount(0, $this->logs());
        $this->assertCount(0, $this->tokens());
    }

    public function test_the_portal_link_template_exists_and_only_uses_allowed_variables(): void
    {
        $template = $this->withoutTenant(fn () => MessageTemplate::query()->whereNull('tenant_id')->forKey(MessageTemplateKey::PortalLink)->first());

        $this->assertNotNull($template);
        $this->assertStringContainsString('{{link}}', $template->body);
        $this->assertSame([], (new MessageTemplateRenderer)->unknownVariables($template->body));
    }

    // ---- Cabut ----

    public function test_revoking_one_link_closes_it_at_once_and_leaves_the_others(): void
    {
        $resident = $this->residentOnly();
        $keep = $this->tokenFor($resident);
        $drop = $this->tokenFor($resident);
        $dropRow = $this->tokens()->last();

        $this->actingAs($this->owner)->delete(route('owner.residents.portal.revoke', [$resident, $dropRow]))
            ->assertRedirect(route('owner.residents.show', $resident));
        auth()->logout();

        $this->get($this->portalUrl($drop))->assertNotFound();
        $this->get($this->portalUrl($keep))->assertOk();
        $this->assertNotNull($this->tokens()->last()->revoked_at);
    }

    public function test_revoking_all_links_closes_every_one(): void
    {
        $resident = $this->residentOnly();
        $links = [$this->tokenFor($resident), $this->tokenFor($resident), $this->tokenFor($resident)];

        $this->actingAs($this->owner)->post(route('owner.residents.portal.revoke-all', $resident))
            ->assertSessionHas('status', fn ($status) => str_contains($status, '3 tautan portal dicabut'));
        auth()->logout();

        foreach ($links as $link) {
            $this->get($this->portalUrl($link))->assertNotFound();
        }
    }

    public function test_revoking_all_only_affects_that_resident(): void
    {
        $a = $this->residentOnly();
        $b = $this->residentOnly();
        $this->tokenFor($a);
        $keep = $this->tokenFor($b);

        $this->actingAs($this->owner)->post(route('owner.residents.portal.revoke-all', $a));
        auth()->logout();

        $this->get($this->portalUrl($keep))->assertOk();
    }

    public function test_a_link_belonging_to_another_resident_cannot_be_revoked_through_this_residents_url(): void
    {
        $a = $this->residentOnly();
        $b = $this->residentOnly();
        $this->tokenFor($b);
        $bRow = $this->tokens()->last();

        $this->actingAs($this->owner)->delete(route('owner.residents.portal.revoke', [$a, $bRow]))->assertNotFound();

        $this->assertNull($this->tokens()->last()->revoked_at);
    }

    // ---- Panel pemilik ----

    public function test_the_resident_profile_lists_active_links_without_any_secret(): void
    {
        $resident = $this->residentOnly();
        $raw = $this->tokenFor($resident);
        $this->tokenFor($resident, null, 'revoked');
        $this->tokenFor($resident, null, 'expired');

        $page = $this->actingAs($this->owner)->get(route('owner.residents.show', $resident))->assertOk();

        $page->assertSee('Portal penghuni')->assertSee('Kirim ulang tautan portal')->assertSee('Cabut semua tautan')->assertSee('belum pernah dibuka')
            ->assertDontSee($raw)->assertDontSee(hash('sha256', $raw));
        $this->assertSame(1, substr_count($page->getContent(), 'belum pernah dibuka'), 'Hanya tautan aktif yang terdaftar.');
    }

    public function test_the_first_send_button_text_when_there_are_no_links(): void
    {
        $resident = $this->residentOnly();

        $this->actingAs($this->owner)->get(route('owner.residents.show', $resident))
            ->assertSee('Kirim tautan portal')->assertSee('Belum ada tautan aktif')->assertDontSee('Cabut semua tautan');
    }

    // ---- Hak akses dan isolasi ----

    public function test_staff_may_manage_links_and_guests_and_other_tenants_may_not(): void
    {
        $resident = $this->residentOnly();
        $staff = $this->staffWith();
        [, $stranger] = $this->tenantWithOwner('business');

        $this->actingAs($staff)->post(route('owner.residents.portal.send', $resident))->assertSessionHasNoErrors();

        $this->actingAs($stranger)->post(route('owner.residents.portal.send', $resident))->assertNotFound();
        $this->actingAs($stranger)->post(route('owner.residents.portal.revoke-all', $resident))->assertNotFound();

        auth()->logout();
        $this->post(route('owner.residents.portal.send', $resident))->assertRedirect(route('login'));
    }

    public function test_another_tenants_token_row_cannot_be_revoked(): void
    {
        $mine = $this->residentOnly();
        [$other, $stranger] = $this->tenantWithOwner('business');
        $lease = $this->leaseFor([], $other);
        $theirResident = $this->asTenant($other, fn () => Resident::findOrFail($lease->primary_resident_id));
        $theirs = $this->tokenFor($theirResident, $other);
        $theirRow = $this->asTenant($other, fn () => PortalToken::firstOrFail());

        $this->actingAs($this->owner)->delete(route('owner.residents.portal.revoke', [$mine, $theirRow]))->assertNotFound();
        $this->actingAs($this->owner)->delete(route('owner.residents.portal.revoke', [$theirResident, $theirRow]))->assertNotFound();

        auth()->logout();
        $this->get($this->portalUrl($theirs))->assertOk();
        $this->assertNotNull($stranger);
    }
}
