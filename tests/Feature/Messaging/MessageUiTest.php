<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\Resident;
use App\Models\UsageCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MessagingFixtures;
use Tests\TestCase;

class MessageUiTest extends TestCase
{
    use MessagingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMessaging();
    }

    private function log(MessageStatus $status = MessageStatus::Failed, array $overrides = [], $tenant = null, ?int $residentId = null): MessageLog
    {
        $tenant ??= $this->tenant;
        $residentId ??= $this->leaseFor([], $tenant)->primary_resident_id;

        return $this->asTenant($tenant, fn () => MessageLog::factory()->for($tenant)->create($overrides + [
            'resident_id' => $residentId,
            'recipient' => '+6281234567890',
            'body' => 'Halo, tagihan Anda sebesar Rp 1.500.000.',
            'status' => $status,
            'error_code' => $status === MessageStatus::Failed ? 'rate_limited' : null,
            'attempts' => $status === MessageStatus::Failed ? 3 : 0,
            'failed_at' => $status === MessageStatus::Failed ? now() : null,
            'scheduled_for' => now(),
        ]));
    }

    // ---- Log dan kuota ----

    public function test_the_log_shows_the_remaining_quota_and_every_message_status_with_text(): void
    {
        $this->setQuota(10);
        $this->log(MessageStatus::Sent, ['counted_in_quota' => true]);
        $this->log(MessageStatus::Failed);
        $this->log(MessageStatus::Skipped, ['skip_reason' => MessageSkipReason::OptedOut]);
        $this->log(MessageStatus::Queued);
        $this->asTenant($this->tenant, fn () => UsageCounter::create(['metric' => 'whatsapp_messages', 'period' => '2026-10', 'used' => 4]));

        $this->actingAs($this->owner)->get(route('owner.messages.index'))->assertOk()
            ->assertSee('data-stat="quota-remaining">6</div>', false)
            ->assertSee('4 terpakai dari 10 pesan')
            ->assertSee('Terkirim')->assertSee('Gagal')->assertSee('Dilewati')->assertSee('Menunggu kirim')
            ->assertSee('Penghuni berhenti menerima pesan')
            ->assertSee('rate_limited')
            ->assertDontSee('Kuota pesan Oktober 2026 habis');
    }

    public function test_an_exhausted_quota_is_announced_to_the_owner(): void
    {
        $this->setQuota(2);
        $this->asTenant($this->tenant, fn () => UsageCounter::create(['metric' => 'whatsapp_messages', 'period' => '2026-10', 'used' => 2]));

        $this->actingAs($this->owner)->get(route('owner.messages.index'))->assertOk()
            ->assertSee('Kuota pesan Oktober 2026 habis')
            ->assertSee('data-stat="quota-remaining">0</div>', false);
    }

    public function test_the_log_only_shows_the_last_four_digits_of_a_number_but_the_full_message(): void
    {
        $this->log(MessageStatus::Sent);

        $this->actingAs($this->owner)->get(route('owner.messages.index'))
            ->assertSee('…7890')->assertDontSee('+6281234567890')->assertSee('Halo, tagihan Anda sebesar Rp 1.500.000.');
    }

    public function test_the_status_filter_works(): void
    {
        $this->log(MessageStatus::Failed, ['body' => 'Pesan gagal ini']);
        $this->log(MessageStatus::Sent, ['body' => 'Pesan sukses ini']);

        $this->actingAs($this->owner)->get(route('owner.messages.index', ['status' => 'failed']))
            ->assertSee('Pesan gagal ini')->assertDontSee('Pesan sukses ini');
        $this->actingAs($this->owner)->get(route('owner.messages.index', ['status' => 'bukan-status']))->assertSessionHasErrors('status');
    }

    // ---- Kirim ulang ----

    public function test_a_failed_message_can_be_resent_from_the_log_and_goes_out(): void
    {
        $failed = $this->log(MessageStatus::Failed);

        $this->actingAs($this->owner)->get(route('owner.messages.index'))->assertSee('Kirim ulang');
        $this->actingAs($this->owner)->post(route('owner.messages.resend', $failed))->assertRedirect(route('owner.messages.index'));

        $sent = $this->freshLog($failed);
        $this->assertSame([MessageStatus::Sent, 1, null, true], [$sent->status, $sent->attempts, $sent->error_code, $sent->counted_in_quota]);
        $this->assertCount(1, $this->provider()->sent());
        $this->assertSame(1, $this->usage());
    }

    public function test_only_failed_messages_can_be_resent(): void
    {
        $sent = $this->log(MessageStatus::Sent);
        $skipped = $this->log(MessageStatus::Skipped, ['skip_reason' => MessageSkipReason::QuotaExceeded]);

        $this->actingAs($this->owner)->get(route('owner.messages.index'))->assertDontSee('Kirim ulang');
        $this->actingAs($this->owner)->post(route('owner.messages.resend', $sent))->assertSessionHasErrors('general');
        $this->actingAs($this->owner)->post(route('owner.messages.resend', $skipped))->assertSessionHasErrors('general');

        $this->assertSame([], $this->provider()->sent());
    }

    public function test_a_message_that_failed_for_lack_of_a_valid_number_cannot_be_resent(): void
    {
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($lease->primary_resident_id)->update(['phone' => '123']));
        $this->openInvoice([], null, $lease);
        $log = $this->logs()->sole();
        $this->assertSame(['invalid_recipient', ''], [$log->error_code, $log->recipient]);

        $this->actingAs($this->owner)->get(route('owner.messages.index'))->assertOk()->assertDontSee('Kirim ulang');
        $this->actingAs($this->owner)->post(route('owner.messages.resend', $log))->assertSessionHasErrors('general');

        $this->assertSame(MessageStatus::Failed, $this->freshLog($log)->status);
        $this->assertSame([], $this->provider()->sent());
        $this->assertSame(0, $this->usage());
    }

    public function test_resending_respects_an_exhausted_quota(): void
    {
        $this->setQuota(0);
        $failed = $this->log(MessageStatus::Failed);

        $this->actingAs($this->owner)->post(route('owner.messages.resend', $failed));

        $this->assertSame(MessageSkipReason::QuotaExceeded, $this->freshLog($failed)->skip_reason);
        $this->assertSame([], $this->provider()->sent());
    }

    // ---- Hak akses dan isolasi ----

    public function test_the_whole_messages_area_needs_the_settings_permission(): void
    {
        $failed = $this->log(MessageStatus::Failed);
        $plain = $this->staffWith();

        $this->actingAs($plain)->get(route('owner.messages.index'))->assertForbidden();
        $this->actingAs($plain)->post(route('owner.messages.resend', $failed))->assertForbidden();
        $this->actingAs($plain)->get(route('owner.message-templates.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('owner.message-templates.edit', 'receipt'))->assertForbidden();
        $this->actingAs($plain)->put(route('owner.message-templates.update', 'receipt'), ['name' => 'X', 'body' => 'Halo {{nama}}'])->assertForbidden();

        $allowed = $this->staffWith(['settings.manage']);
        $this->actingAs($allowed)->get(route('owner.messages.index'))->assertOk();
        $this->actingAs($allowed)->get(route('owner.message-templates.index'))->assertOk();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('owner.messages.index'))->assertRedirect(route('login'));
        $this->get(route('owner.message-templates.index'))->assertRedirect(route('login'));
    }

    public function test_the_menu_shows_messages_only_to_those_who_may_open_them(): void
    {
        $this->actingAs($this->staffWith())->get(route('owner.dashboard'))->assertDontSee(route('owner.messages.index'), false);
        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee(route('owner.messages.index'), false);
    }

    public function test_another_tenant_never_sees_or_resends_my_messages(): void
    {
        $mine = $this->log(MessageStatus::Failed, ['body' => 'Rahasia tenant A']);
        [$other, $stranger] = $this->tenantWithOwner('business');
        $this->log(MessageStatus::Failed, ['body' => 'Milik tenant B'], $other);

        $this->actingAs($stranger)->get(route('owner.messages.index'))->assertOk()
            ->assertSee('Milik tenant B')->assertDontSee('Rahasia tenant A');
        $this->actingAs($stranger)->post(route('owner.messages.resend', $mine))->assertNotFound();

        $this->assertSame(MessageStatus::Failed, $this->freshLog($mine)->status);
        $this->assertSame([], $this->provider()->sent());
    }

    // ---- Template di UI ----

    public function test_the_template_list_marks_edited_and_disabled_templates(): void
    {
        $this->actingAs($this->owner)->get(route('owner.message-templates.index'))->assertOk()
            ->assertSee('Tagihan terbit')->assertSee('Kuitansi pembayaran')->assertSee('Perubahan status komplain')
            ->assertDontSee('Diubah')->assertDontSee('Nonaktif');

        $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'receipt'), ['name' => 'Kuitansi', 'body' => 'Halo {{nama}}', 'is_active' => 0]);

        $this->actingAs($this->owner)->get(route('owner.message-templates.index'))->assertSee('Diubah')->assertSee('Nonaktif');
    }

    public function test_the_edit_page_lists_the_allowed_variables_and_the_utility_category(): void
    {
        $this->actingAs($this->owner)->get(route('owner.message-templates.edit', 'invoice_issued'))->assertOk()
            ->assertSee('{{ nama }}', false)->assertSee('{{ jumlah }}', false)->assertSee('{{ jatuh_tempo }}', false)->assertSee('{{ link }}', false)
            ->assertSee('utility');
    }

    // ---- Berhenti menerima pesan ----

    public function test_the_owner_can_record_that_a_resident_stopped_receiving_messages_and_it_takes_effect(): void
    {
        $lease = $this->leaseFor();

        $this->actingAs($this->owner)->post(route('owner.residents.whatsapp', $lease->primary_resident_id), ['opt_out' => 1])
            ->assertRedirect(route('owner.residents.show', $lease->primary_resident_id));

        $resident = $this->asTenant($this->tenant, fn () => Resident::findOrFail($lease->primary_resident_id));
        $this->assertNotNull($resident->whatsapp_opt_out_at);
        $this->actingAs($this->owner)->get(route('owner.residents.show', $resident))->assertSee('Berhenti menerima pesan')->assertSee('Aktifkan kembali pesan otomatis');

        $this->openInvoice([], null, $lease);
        $log = $this->logs()->sole();
        $this->assertSame([MessageStatus::Skipped, MessageSkipReason::OptedOut], [$log->status, $log->skip_reason]);

        $this->actingAs($this->owner)->post(route('owner.residents.whatsapp', $resident), ['opt_out' => 0]);
        $this->assertNull($this->asTenant($this->tenant, fn () => Resident::findOrFail($lease->primary_resident_id))->whatsapp_opt_out_at);
    }

    public function test_opting_out_keeps_the_original_timestamp_when_repeated(): void
    {
        $lease = $this->leaseFor();
        $this->actingAs($this->owner)->post(route('owner.residents.whatsapp', $lease->primary_resident_id), ['opt_out' => 1]);
        $first = $this->asTenant($this->tenant, fn () => Resident::findOrFail($lease->primary_resident_id))->whatsapp_opt_out_at;

        $this->travelTo(now()->addDay());
        $this->actingAs($this->owner)->post(route('owner.residents.whatsapp', $lease->primary_resident_id), ['opt_out' => 1]);

        $this->assertEquals($first, $this->asTenant($this->tenant, fn () => Resident::findOrFail($lease->primary_resident_id))->whatsapp_opt_out_at);
    }

    public function test_the_opt_out_switch_is_tenant_scoped(): void
    {
        $lease = $this->leaseFor();
        [, $stranger] = $this->tenantWithOwner('business');

        $this->actingAs($stranger)->post(route('owner.residents.whatsapp', $lease->primary_resident_id), ['opt_out' => 1])->assertNotFound();
        $this->assertNull($this->asTenant($this->tenant, fn () => Resident::findOrFail($lease->primary_resident_id))->whatsapp_opt_out_at);
    }

    public function test_the_opt_out_switch_needs_login(): void
    {
        $lease = $this->leaseFor();

        $this->post(route('owner.residents.whatsapp', $lease->primary_resident_id), ['opt_out' => 1])->assertRedirect(route('login'));
    }
}
