<?php

namespace Tests\Feature\Portal;

use App\Enums\TenantStatus;
use App\Models\PortalToken;
use App\Models\Resident;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\PortalFixtures;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use PortalFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPortal();
    }

    private function clearFailures(): void
    {
        RateLimiter::clear('portal-token-failures:127.0.0.1');
    }

    // ---- Token ----

    public function test_a_valid_token_opens_the_residents_home(): void
    {
        [$token, $invoice, $resident] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token))->assertOk()
            ->assertSee($resident->name)
            ->assertSee($invoice->number)
            ->assertSee('data-stat="outstanding">Rp 1.500.000</div>', false);
    }

    public function test_tokens_are_long_random_and_only_their_hash_is_stored(): void
    {
        [$token] = $this->residentWithInvoice();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);

        $rows = $this->withoutTenant(fn () => DB::table('portal_tokens')->get());
        $this->assertCount(1, $rows);
        $this->assertSame(hash('sha256', $token), $rows[0]->token_hash);
        $this->assertStringNotContainsString($token, json_encode($rows));

        $this->assertNotSame(PortalToken::generate(), PortalToken::generate());
    }

    public function test_every_kind_of_invalid_token_gets_the_exact_same_response(): void
    {
        [$valid, , $resident] = $this->residentWithInvoice();
        $revoked = $this->tokenFor($resident, null, 'revoked');
        $expired = $this->tokenFor($resident, null, 'expired');
        $deletedResident = $this->asTenant($this->tenant, fn () => Resident::factory()->for($this->tenant)->create());
        $orphan = $this->tokenFor($deletedResident);
        $this->asTenant($this->tenant, fn () => $deletedResident->delete());

        $responses = [];

        foreach ([
            'tidak-ada' => str_repeat('a', 64),
            'terlalu-pendek' => 'abc',
            'terlalu-panjang' => str_repeat('a', 65),
            'karakter-aneh' => str_repeat('a', 63).'!',
            'dicabut' => $revoked,
            'kedaluwarsa' => $expired,
            'penghuni-dihapus' => $orphan,
        ] as $label => $token) {
            $responses[$label] = $this->get($this->portalUrl($token));
            $this->clearFailures();
        }

        foreach ($responses as $label => $response) {
            $response->assertNotFound();
            $this->assertSame($responses['tidak-ada']->getContent(), $response->getContent(), "Respons untuk {$label} berbeda dari token yang tidak ada.");
        }

        $this->assertStringContainsString('Tautan tidak berlaku', $responses['dicabut']->getContent());
        $this->assertStringNotContainsString('dicabut', strtolower(strip_tags($responses['dicabut']->getContent())));
        $this->assertStringNotContainsString('kedaluwarsa', strtolower(strip_tags($responses['kedaluwarsa']->getContent())));

        $this->get($this->portalUrl($valid))->assertOk();
    }

    public function test_the_same_response_applies_to_every_portal_page_not_just_the_home(): void
    {
        foreach (['', '/tagihan', '/tagihan/1', '/pembayaran', '/kuitansi/1', '/kontrak', '/komplain', '/komplain/baru', '/komplain/1', '/rekening/1/qris'] as $path) {
            $this->get($this->portalUrl(str_repeat('b', 64), $path))->assertNotFound()->assertSee('Tautan tidak berlaku');
            $this->clearFailures();
        }
    }

    public function test_revoking_a_token_closes_access_immediately(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $this->get($this->portalUrl($token))->assertOk();

        $this->asTenant($this->tenant, fn () => PortalToken::query()->where('resident_id', $resident->id)->update(['revoked_at' => now()]));

        $this->get($this->portalUrl($token))->assertNotFound()->assertSee('Tautan tidak berlaku');
        $this->get($this->portalUrl($token, '/tagihan'))->assertNotFound();
    }

    public function test_a_token_stops_working_the_moment_it_expires(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $this->asTenant($this->tenant, fn () => PortalToken::query()->where('resident_id', $resident->id)->update(['expires_at' => now()->addMinute()]));
        $this->get($this->portalUrl($token))->assertOk();

        $this->travel(2)->minutes();

        $this->get($this->portalUrl($token))->assertNotFound();
    }

    public function test_a_token_of_another_tenant_never_opens_this_tenants_data(): void
    {
        [$mine, $myInvoice, $myResident] = $this->residentWithInvoice();
        [$other] = $this->tenantWithOwner('business');
        [$theirs, $theirInvoice, $theirResident] = $this->residentWithInvoice([], $other);

        // Nomor tagihan hanya unik per tenant, jadi kedua tenant punya INV-2026-10-0001: dibedakan lewat penghuni.
        $this->get($this->portalUrl($theirs))->assertOk()->assertSee($theirResident->name)->assertDontSee($myResident->name);
        $this->get($this->portalUrl($mine))->assertOk()->assertSee($myResident->name)->assertDontSee($theirResident->name);

        $this->get($this->portalUrl($theirs, '/tagihan/'.$myInvoice->id))->assertNotFound();
        $this->get($this->portalUrl($mine, '/tagihan/'.$theirInvoice->id))->assertNotFound();
    }

    public function test_a_closed_tenants_links_stop_working_and_a_suspended_one_is_read_only(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();

        $this->tenant->update(['status' => TenantStatus::Suspended]);
        $this->get($this->portalUrl($token))->assertOk();
        $this->post($this->portalUrl($token, '/tagihan/'.$invoice->id.'/bukti'), [])->assertNotFound();
        $this->post($this->portalUrl($token, '/komplain'), ['category' => 'water', 'title' => 'Air mati', 'description' => 'Air mati sejak pagi'])->assertNotFound();

        $this->tenant->update(['status' => TenantStatus::Closed]);
        $this->get($this->portalUrl($token))->assertNotFound()->assertSee('Tautan tidak berlaku');
    }

    public function test_opening_the_portal_records_last_used_without_writing_on_every_request(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $row = fn () => $this->asTenant($this->tenant, fn () => PortalToken::query()->where('resident_id', $resident->id)->firstOrFail());
        $this->assertNull($row()->last_used_at);

        $this->get($this->portalUrl($token))->assertOk();
        $first = $row()->last_used_at;
        $this->assertNotNull($first);

        $this->travel(10)->seconds();
        $this->get($this->portalUrl($token))->assertOk();
        $this->assertEquals($first, $row()->last_used_at, 'Dalam semenit tidak ditulis ulang.');

        $this->travel(2)->minutes();
        $this->get($this->portalUrl($token))->assertOk();
        $this->assertTrue($row()->last_used_at->gt($first));
    }

    // ---- Pembatasan laju ----

    public function test_repeated_failures_from_one_address_are_blocked(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get($this->portalUrl(str_repeat((string) $i, 64)))->assertNotFound();
        }

        $this->get($this->portalUrl(str_repeat('z', 64)))->assertStatus(429)->assertSee('Tautan tidak berlaku');
    }

    public function test_a_blocked_address_recovers_after_the_window(): void
    {
        [$token] = $this->residentWithInvoice();
        for ($i = 0; $i < 10; $i++) {
            $this->get($this->portalUrl(str_repeat((string) $i, 64)));
        }
        $this->get($this->portalUrl($token))->assertStatus(429);

        $this->travel(61)->seconds();

        $this->get($this->portalUrl($token))->assertOk();
    }

    public function test_the_general_portal_throttle_applies(): void
    {
        config(['kostra.portal.requests_per_minute' => 5]);
        [$token] = $this->residentWithInvoice();

        for ($i = 0; $i < 5; $i++) {
            $this->get($this->portalUrl($token, '/kontrak'))->assertOk();
        }

        $this->get($this->portalUrl($token, '/kontrak'))->assertStatus(429);
    }

    // ---- Token tidak boleh tersimpan di sesi ----

    public function test_the_real_token_never_lands_in_the_session(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $dump = fn () => json_encode(session()->all());

        $this->get($this->portalUrl($token))->assertOk();
        $this->assertStringNotContainsString($token, $dump());
        $this->assertNull(session()->get('_previous'));

        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertOk();
        $this->assertStringNotContainsString($token, $dump());

        // Galat validasi mengalihkan dengan flash galat dan input lama, tetapi tanpa URL berisi token.
        $this->post($this->portalUrl($token, '/tagihan/'.$invoice->id.'/bukti'), ['amount' => '0'])->assertSessionHasErrors();
        $this->assertStringNotContainsString($token, $dump());

        $this->get($this->portalUrl($token, '/komplain/baru'))->assertOk();
        $this->assertStringNotContainsString($token, $dump());
    }

    public function test_portal_forms_still_have_a_working_csrf_token_and_flash_messages(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();

        $page = $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertOk();
        $this->assertStringContainsString('name="_token"', $page->getContent());

        $this->post($this->portalUrl($token, '/komplain'), ['category' => 'water', 'title' => 'Air mati', 'description' => 'Air mati sejak pagi'])
            ->assertSessionHas('status');
    }

    // ---- Pemisahan dari panel pemilik ----

    public function test_a_portal_visit_grants_no_access_to_owner_or_admin_areas(): void
    {
        [$token] = $this->residentWithInvoice();
        $this->get($this->portalUrl($token))->assertOk();

        $this->get('/owner')->assertRedirect(route('login'));
        $this->get('/owner/residents')->assertRedirect(route('login'));
        $this->get('/owner/payments')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect();
        $this->assertGuest();
    }

    public function test_portal_pages_are_not_cacheable_not_indexable_and_do_not_leak_the_token_by_referrer(): void
    {
        [$token] = $this->residentWithInvoice();

        $response = $this->get($this->portalUrl($token))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertSee('<meta name="referrer" content="no-referrer">', false);

        $invalid = $this->get($this->portalUrl(str_repeat('c', 64)));
        $this->assertStringContainsString('no-store', $invalid->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $invalid->headers->get('Referrer-Policy'));
    }

    public function test_the_tenant_context_does_not_leak_out_of_a_portal_request(): void
    {
        [$token] = $this->residentWithInvoice();
        $this->get($this->portalUrl($token))->assertOk();

        $this->assertNull(app(TenantContext::class)->id());
    }
}
