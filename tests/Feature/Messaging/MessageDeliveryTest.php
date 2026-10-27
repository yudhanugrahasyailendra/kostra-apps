<?php

namespace Tests\Feature\Messaging;

use App\Actions\Messaging\ApplyDeliveryStatus;
use App\Actions\Messaging\DeliverWhatsAppMessage;
use App\Contracts\WhatsAppProvider;
use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\TenantStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\MessageLog;
use App\Models\Resident;
use App\Models\UsageCounter;
use App\Services\Messaging\OutboundMessage;
use App\Services\Messaging\SendResult;
use App\Services\Messaging\StatusUpdate;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\MessagingFixtures;
use Tests\TestCase;

class MessageDeliveryTest extends TestCase
{
    use MessagingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMessaging();
        // Antrean dikendalikan tes: pengiriman dijalankan manual lewat deliver().
        Queue::fake();
    }

    private function queuedLog(array $leaseExtra = []): MessageLog
    {
        $this->openInvoice($leaseExtra);

        return $this->logs()->last();
    }

    // ---- Pengiriman dasar ----

    public function test_a_queued_message_is_logged_in_full_then_sent_and_counted(): void
    {
        $invoice = $this->openInvoice();
        $resident = $this->residentOf($invoice);

        $log = $this->logs()->sole();
        $this->assertSame([MessageStatus::Queued, 'whatsapp', 'utility', 'invoice', $invoice->id, $resident->id], [
            $log->status, $log->channel, $log->category, $log->subject_type, $log->subject_id, $log->resident_id,
        ]);
        $this->assertSame($resident->phone, $log->recipient);
        $this->assertStringContainsString($resident->name, $log->body);
        $this->assertStringContainsString('Rp 1.500.000', $log->body);
        $this->assertStringNotContainsString('{{', $log->body);
        Queue::assertPushed(SendWhatsAppMessage::class, 1);

        $this->assertTrue($this->deliver($log)->isDone());

        $sent = $this->freshLog($log);
        $this->assertSame([MessageStatus::Sent, 'fake', 1, true], [$sent->status, $sent->provider, $sent->attempts, $sent->counted_in_quota]);
        $this->assertStringStartsWith('fake-', $sent->provider_message_id);
        $this->assertNotNull($sent->sent_at);
        $this->assertSame(1, $this->usage());
        $this->assertCount(1, $this->provider()->sent());
        $this->assertSame([$resident->phone, $log->body], [$this->provider()->sent()[0]->to, $this->provider()->sent()[0]->body]);
    }

    public function test_delivering_the_same_message_twice_never_sends_twice(): void
    {
        $log = $this->queuedLog();

        $this->deliver($log);
        $this->deliver($log);
        $this->deliver($log);

        $this->assertCount(1, $this->provider()->sent());
        $this->assertSame(1, $this->usage());
        $this->assertSame(1, $this->freshLog($log)->attempts);
    }

    public function test_a_normalised_international_number_is_what_gets_stored_and_sent(): void
    {
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($lease->primary_resident_id)->update(['phone' => '0812-3456-7890']));
        $this->openInvoice([], null, $lease);

        $this->assertSame('+6281234567890', $this->logs()->sole()->recipient);
    }

    public function test_an_unusable_phone_number_is_logged_as_failed_without_queueing(): void
    {
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($lease->primary_resident_id)->update(['phone' => '123']));
        $this->openInvoice([], null, $lease);

        $log = $this->logs()->sole();
        $this->assertSame([MessageStatus::Failed, 'invalid_recipient', 0], [$log->status, $log->error_code, $this->usage()]);
        Queue::assertNothingPushed();
    }

    // ---- Opt-out ----

    public function test_a_resident_who_opted_out_is_skipped_and_nothing_is_sent_or_counted(): void
    {
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($lease->primary_resident_id)->update(['whatsapp_opt_out_at' => now()]));

        $this->openInvoice([], null, $lease);

        $log = $this->logs()->sole();
        $this->assertSame([MessageStatus::Skipped, MessageSkipReason::OptedOut, false], [$log->status, $log->skip_reason, $log->counted_in_quota]);
        $this->assertSame([], $this->provider()->sent());
        $this->assertSame(0, $this->usage());
        Queue::assertNothingPushed();
    }

    public function test_opting_out_after_a_message_was_queued_still_stops_it(): void
    {
        $log = $this->queuedLog();
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($log->resident_id)->update(['whatsapp_opt_out_at' => now()]));

        $this->deliver($log);

        $fresh = $this->freshLog($log);
        $this->assertSame([MessageStatus::Skipped, MessageSkipReason::OptedOut], [$fresh->status, $fresh->skip_reason]);
        $this->assertSame([], $this->provider()->sent());
        $this->assertSame(0, $this->usage());
    }

    // ---- Kuota ----

    public function test_an_exhausted_quota_skips_the_message_with_a_reason(): void
    {
        $this->setQuota(1);
        $first = $this->queuedLog();
        $second = $this->queuedLog();

        $this->deliver($first);
        $this->deliver($second);

        $this->assertSame(MessageStatus::Sent, $this->freshLog($first)->status);
        $skipped = $this->freshLog($second);
        $this->assertSame([MessageStatus::Skipped, MessageSkipReason::QuotaExceeded, false], [$skipped->status, $skipped->skip_reason, $skipped->counted_in_quota]);
        $this->assertSame(1, $this->usage());
        $this->assertCount(1, $this->provider()->sent());
    }

    public function test_a_plan_without_a_quota_never_sends(): void
    {
        $this->setQuota(0);
        $log = $this->queuedLog();

        $this->deliver($log);

        $this->assertSame(MessageSkipReason::QuotaExceeded, $this->freshLog($log)->skip_reason);
        $this->assertSame([], $this->provider()->sent());
        $this->assertSame(0, $this->usage());
    }

    public function test_twenty_parallel_jobs_with_five_left_send_exactly_five(): void
    {
        $this->setQuota(5);
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => MessageLog::factory()->for($this->tenant)->count(20)->create([
            'resident_id' => $lease->primary_resident_id,
            'recipient' => '+6281234567890',
            'body' => 'Halo',
            'status' => MessageStatus::Queued,
            'scheduled_for' => now(),
        ]));

        foreach ($this->logs() as $log) {
            $this->deliver($log);
        }

        $counts = $this->logs()->countBy(fn (MessageLog $log) => $log->status->value)->all();
        $this->assertSame(['sent' => 5, 'skipped' => 15], $counts);
        $this->assertSame(15, $this->logs()->where('skip_reason', MessageSkipReason::QuotaExceeded)->count());
        $this->assertSame(5, $this->usage());
        $this->assertCount(5, $this->provider()->sent());
    }

    public function test_the_quota_is_taken_with_one_conditional_update_never_read_then_write(): void
    {
        $this->setQuota(10);
        $log = $this->queuedLog();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'usage_counters')) {
                $queries[] = $query->sql;
            }
        });

        $this->deliver($log);

        $this->assertCount(2, $queries, 'Hanya buat-baris-bila-belum-ada dan satu UPDATE bersyarat yang menyentuh usage_counters.');
        $this->assertMatchesRegularExpression('/^insert (ignore )?into [`"]usage_counters[`"]/i', $queries[0]);
        $this->assertMatchesRegularExpression('/^update [`"]usage_counters[`"] set [`"]used[`"] = [`"]used[`"] \+ 1.* where .*[`"]used[`"] < \?/s', $queries[1]);
        $this->assertSame(0, count(array_filter($queries, fn ($sql) => str_starts_with(strtolower($sql), 'select'))));
    }

    public function test_quota_counts_per_tenant_and_per_month(): void
    {
        $this->setQuota(10);
        [$other] = $this->tenantWithOwner('business');
        $log = $this->queuedLog();
        $this->deliver($log);

        $this->assertSame([1, 0], [$this->usage(), $this->usage($other)]);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 03:00:00', 'UTC'));
        $next = $this->queuedLog();
        $this->deliver($next);

        $periods = $this->asTenant($this->tenant, fn () => UsageCounter::query()->orderBy('period')->pluck('used', 'period')->all());
        $this->assertSame(['2026-10' => 1, '2026-11' => 1], $periods);
    }

    public function test_a_refund_after_midnight_of_the_month_end_goes_back_to_the_month_it_was_taken_from(): void
    {
        $this->setQuota(10);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00:00', 'UTC')); // 18:00 WITA, 31 Oktober
        $log = $this->queuedLog();
        $this->deliver($log);
        $providerId = $this->freshLog($log)->provider_message_id;
        $this->assertSame(['2026-10' => 1], $this->periods());

        $this->travelTo(CarbonImmutable::parse('2026-11-01 04:00:00', 'UTC')); // 12:00 WITA, 1 November
        app(ApplyDeliveryStatus::class)(new StatusUpdate($providerId, StatusUpdate::FAILED, 'x'));

        $this->assertSame(['2026-10' => 0], $this->periods(), 'Kuota Oktober kembali; November tidak ikut berkurang.');
    }

    /**
     * @return array<string, int>
     */
    private function periods(): array
    {
        return $this->asTenant($this->tenant, fn () => UsageCounter::query()->orderBy('period')->pluck('used', 'period')->all());
    }

    // ---- Jam kirim ----

    public function test_a_message_outside_the_send_window_is_deferred_not_dropped(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 15:30:00', 'UTC')); // 23:30 WITA

        $log = $this->queuedLog();

        $this->assertSame(MessageStatus::Queued, $log->status);
        $this->assertTrue($log->scheduled_for->equalTo(CarbonImmutable::parse('2026-10-16 00:00:00', 'UTC')));
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->delay instanceof DateTimeInterface
            && CarbonImmutable::instance($job->delay)->equalTo(CarbonImmutable::parse('2026-10-16 00:00:00', 'UTC')));

        // Bila job tetap berjalan di luar jam kirim, pesan dikembalikan ke antrean, tidak dikirim dan tidak memakai kuota.
        $result = $this->deliver($log);
        $this->assertFalse($result->isDone());
        $this->assertEqualsWithDelta(8.5 * 3600, $result->releaseSeconds, 5);
        $this->assertSame(MessageStatus::Queued, $this->freshLog($log)->status);
        $this->assertSame([[], 0], [$this->provider()->sent(), $this->usage()]);

        $this->travelTo(CarbonImmutable::parse('2026-10-16 00:05:00', 'UTC')); // 08:05 WITA
        $this->assertTrue($this->deliver($log)->isDone());
        $this->assertSame(MessageStatus::Sent, $this->freshLog($log)->status);
    }

    public function test_a_message_before_the_window_opens_waits_for_the_same_morning(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 23:00:00', 'UTC')); // 07:00 WITA 15 Oktober

        $log = $this->queuedLog();

        $this->assertTrue($log->scheduled_for->equalTo(CarbonImmutable::parse('2026-10-15 00:00:00', 'UTC')));
        $this->assertFalse($this->deliver($log)->isDone());
        $this->assertSame([], $this->provider()->sent());
    }

    public function test_a_message_inside_the_window_is_not_delayed(): void
    {
        $log = $this->queuedLog();

        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->delay === null);
        $this->assertTrue($log->scheduled_for->lessThanOrEqualTo(now()));
    }

    // ---- Kegagalan dan percobaan ulang ----

    public function test_a_failing_provider_is_retried_up_to_the_limit_then_marked_failed_and_the_quota_returned(): void
    {
        $log = $this->queuedLog();
        $this->provider()->failNext(10, 'rate_limited');

        $first = $this->deliver($log);
        $this->assertSame([false, 60], [$first->isDone(), $first->releaseSeconds]);
        $mid = $this->freshLog($log);
        $this->assertSame([MessageStatus::Queued, 1, 'rate_limited', true, 1], [$mid->status, $mid->attempts, $mid->error_code, $mid->counted_in_quota, $this->usage()]);

        $second = $this->deliver($log);
        $this->assertSame([false, 300], [$second->isDone(), $second->releaseSeconds]);
        $this->assertSame(2, $this->freshLog($log)->attempts);

        $this->assertTrue($this->deliver($log)->isDone());
        $failed = $this->freshLog($log);
        $this->assertSame([MessageStatus::Failed, 3, 'rate_limited', false], [$failed->status, $failed->attempts, $failed->error_code, $failed->counted_in_quota]);
        $this->assertNotNull($failed->failed_at);
        $this->assertSame(0, $this->usage(), 'Pesan yang gagal tidak memakan kuota.');
        $this->assertSame([], $this->provider()->sent());
    }

    public function test_a_permanent_rejection_fails_immediately(): void
    {
        $log = $this->queuedLog();
        $this->provider()->failNext(1, 'invalid_number', false);

        $this->assertTrue($this->deliver($log)->isDone());

        $failed = $this->freshLog($log);
        $this->assertSame([MessageStatus::Failed, 1, 'invalid_number'], [$failed->status, $failed->attempts, $failed->error_code]);
        $this->assertSame(0, $this->usage());
    }

    public function test_a_retry_that_succeeds_uses_the_quota_only_once(): void
    {
        $log = $this->queuedLog();
        $this->provider()->failNext(1);

        $this->assertFalse($this->deliver($log)->isDone());
        $this->assertTrue($this->deliver($log)->isDone());

        $sent = $this->freshLog($log);
        $this->assertSame([MessageStatus::Sent, 2, null], [$sent->status, $sent->attempts, $sent->error_code]);
        $this->assertSame(1, $this->usage());
        $this->assertCount(1, $this->provider()->sent());
    }

    public function test_a_provider_that_throws_is_a_retryable_failure_without_leaking_the_error_text(): void
    {
        $log = $this->queuedLog();
        app()->instance(WhatsAppProvider::class, new class implements WhatsAppProvider
        {
            public function name(): string
            {
                return 'rusak';
            }

            public function send(OutboundMessage $message): SendResult
            {
                throw new RuntimeException('gagal kirim ke +6281234567890');
            }

            public function parseStatusWebhook(array $payload): ?StatusUpdate
            {
                return null;
            }
        });

        $this->assertFalse($this->deliver($log)->isDone());

        $fresh = $this->freshLog($log);
        $this->assertSame(['provider_exception', 'RuntimeException', 1], [$fresh->error_code, $fresh->error_message, $fresh->attempts]);
        $this->assertStringNotContainsString('628', (string) $fresh->error_message);
    }

    public function test_messages_of_a_suspended_tenant_are_not_sent(): void
    {
        $log = $this->queuedLog();
        $this->tenant->update(['status' => TenantStatus::Suspended]);

        $this->deliver($log);

        $failed = $this->freshLog($log);
        $this->assertSame([MessageStatus::Failed, 'tenant_inactive', 0], [$failed->status, $failed->error_code, $this->usage()]);
        $this->assertSame([], $this->provider()->sent());
    }

    // ---- Job ----

    public function test_the_job_sets_the_tenant_explicitly_and_sends(): void
    {
        $log = $this->queuedLog();

        $job = new SendWhatsAppMessage($log->id, $this->tenant->id);
        app(TenantContext::class)->forget(); // tanpa pengguna atau konteks: seperti di pekerja antrean
        $job->handle(app(TenantContext::class), app(DeliverWhatsAppMessage::class));

        $this->assertSame(MessageStatus::Sent, $this->freshLog($log)->status);
    }

    public function test_the_job_for_a_missing_tenant_or_log_does_nothing(): void
    {
        $log = $this->queuedLog();

        (new SendWhatsAppMessage($log->id, 999999))->handle(app(TenantContext::class), app(DeliverWhatsAppMessage::class));
        (new SendWhatsAppMessage(999999, $this->tenant->id))->handle(app(TenantContext::class), app(DeliverWhatsAppMessage::class));

        $this->assertSame(MessageStatus::Queued, $this->freshLog($log)->status);
    }

    public function test_when_the_queue_gives_up_the_message_is_failed_and_the_quota_returned(): void
    {
        $log = $this->queuedLog();
        $this->asTenant($this->tenant, fn () => $log->update(['counted_in_quota' => true]));
        $this->asTenant($this->tenant, fn () => UsageCounter::create(['metric' => 'whatsapp_messages', 'period' => '2026-10', 'used' => 1]));

        (new SendWhatsAppMessage($log->id, $this->tenant->id))->failed(new RuntimeException('detail rahasia 0812'));

        $failed = $this->freshLog($log);
        $this->assertSame([MessageStatus::Failed, 'job_failed', 'RuntimeException', 0], [$failed->status, $failed->error_code, $failed->error_message, $this->usage()]);
    }

    // ---- Webhook status ----

    public function test_a_delivered_webhook_marks_the_message_delivered(): void
    {
        $log = $this->queuedLog();
        $this->deliver($log);
        $providerId = $this->freshLog($log)->provider_message_id;

        $update = $this->provider()->parseStatusWebhook(['id' => $providerId, 'status' => 'delivered']);
        app(ApplyDeliveryStatus::class)($update);

        $delivered = $this->freshLog($log);
        $this->assertSame(MessageStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertSame(1, $this->usage());
    }

    public function test_a_failed_webhook_marks_failed_and_returns_the_quota_but_never_downgrades_delivered(): void
    {
        $log = $this->queuedLog();
        $other = $this->queuedLog();
        $this->deliver($log);
        $this->deliver($other);
        $failedId = $this->freshLog($log)->provider_message_id;
        $deliveredId = $this->freshLog($other)->provider_message_id;
        $this->assertSame(2, $this->usage());

        app(ApplyDeliveryStatus::class)(new StatusUpdate($failedId, StatusUpdate::FAILED, '131026', 'Penerima tidak terjangkau'));
        app(ApplyDeliveryStatus::class)(new StatusUpdate($deliveredId, StatusUpdate::DELIVERED));
        app(ApplyDeliveryStatus::class)(new StatusUpdate($deliveredId, StatusUpdate::FAILED, 'x'));

        $failed = $this->freshLog($log);
        $this->assertSame([MessageStatus::Failed, '131026', false], [$failed->status, $failed->error_code, $failed->counted_in_quota]);
        $this->assertSame(MessageStatus::Delivered, $this->freshLog($other)->status);
        $this->assertSame(1, $this->usage());
    }

    public function test_a_webhook_for_an_unknown_message_is_ignored_and_only_touches_its_own_tenant(): void
    {
        $this->assertNull(app(ApplyDeliveryStatus::class)(new StatusUpdate('tidak-ada', StatusUpdate::DELIVERED)));

        [$other] = $this->tenantWithOwner('business');
        $theirs = $this->asTenant($other, fn () => MessageLog::factory()->for($other)->create(['status' => MessageStatus::Sent, 'provider_message_id' => 'milik-tetangga']));
        $mine = $this->queuedLog();
        $this->deliver($mine);

        app(ApplyDeliveryStatus::class)(new StatusUpdate($this->freshLog($mine)->provider_message_id, StatusUpdate::DELIVERED));

        $this->assertSame(MessageStatus::Sent, $this->asTenant($other, fn () => MessageLog::findOrFail($theirs->id))->status);
    }

    // ---- Template nonaktif ----

    public function test_a_template_the_owner_switched_off_sends_nothing(): void
    {
        $this->actingAs($this->owner)->put(route('owner.message-templates.update', 'invoice_issued'), [
            'name' => 'Tagihan terbit', 'body' => 'Halo {{nama}}', 'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->openInvoice();

        $this->assertCount(0, $this->logs());
        Queue::assertNothingPushed();
    }
}
