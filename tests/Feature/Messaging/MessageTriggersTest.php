<?php

namespace Tests\Feature\Messaging;

use App\Actions\Billing\CreateManualInvoice;
use App\Actions\Complaints\ChangeComplaintStatus;
use App\Actions\Messaging\QueueDueReminders;
use App\Actions\Messaging\QueueLeaseExpiringReminders;
use App\Enums\ComplaintStatus;
use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\TenantStatus;
use App\Jobs\SendTenantReminders;
use App\Models\BillingSetting;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Resident;
use App\Models\UsageCounter;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Concerns\MessagingFixtures;
use Tests\TestCase;

class MessageTriggersTest extends TestCase
{
    use MessagingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMessaging();
    }

    private function clearMessages(): void
    {
        $this->withoutTenant(function () {
            MessageLog::query()->delete();
            UsageCounter::query()->delete();
        });
        $this->provider()->reset();
    }

    /**
     * @return array<string, list<int>> kunci template => id tagihan yang diingatkan
     */
    private function remindedByKey(): array
    {
        return $this->logs()->groupBy(fn (MessageLog $log) => $log->template->key)
            ->map(fn ($group) => $group->pluck('subject_id')->sort()->values()->all())->all();
    }

    private function runReminders(): int
    {
        return $this->asTenant($this->tenant, fn () => app(QueueDueReminders::class)());
    }

    // ---- Pemicu event ----

    public function test_an_issued_invoice_sends_its_message_through_the_whole_pipeline(): void
    {
        $invoice = $this->openInvoice();

        $log = $this->logs()->sole();
        $this->assertSame(['invoice_issued', MessageStatus::Sent, 'invoice', $invoice->id], [$log->template->key, $log->status, $log->subject_type, $log->subject_id]);
        $this->assertCount(1, $this->provider()->sent());
        $this->assertSame(1, $this->usage());
    }

    public function test_a_manual_invoice_also_triggers_the_message(): void
    {
        $lease = $this->leaseFor();
        $this->asTenant($this->tenant, fn () => app(CreateManualInvoice::class)($lease, [
            'items' => [['description' => 'Biaya laundry', 'quantity' => 1, 'unit_price' => 75_000, 'type' => 'other']],
            'due_date' => '2026-10-20',
        ], $this->owner));

        $log = $this->logs()->sole();
        $this->assertSame('invoice_issued', $log->template->key);
        $this->assertStringContainsString('Rp 75.000', $log->body);
    }

    public function test_recording_a_payment_sends_the_receipt(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 600_000]]);

        $receipt = $this->logs()->last();
        $this->assertSame(['receipt', MessageStatus::Sent, 'payment'], [$receipt->template->key, $receipt->status, $receipt->subject_type]);
        $this->assertStringContainsString('Rp 600.000', $receipt->body);
        $this->assertCount(2, $this->provider()->sent());
        $this->assertSame(2, $this->usage());
    }

    public function test_a_complaint_status_change_tells_the_resident(): void
    {
        $lease = $this->leaseFor();
        $complaint = $this->asTenant($this->tenant, fn () => Complaint::factory()->for($this->tenant)
            ->for($lease->property, 'property')->for($lease->room, 'room')->for($lease->primaryResident, 'resident')->create());

        $this->asTenant($this->tenant, fn () => app(ChangeComplaintStatus::class)($complaint, ComplaintStatus::InProgress));

        $log = $this->logs()->sole();
        $this->assertSame(['complaint_status', 'complaint', MessageStatus::Sent], [$log->template->key, $log->subject_type, $log->status]);
        $this->assertStringContainsString('Diproses', $log->body);

        $this->asTenant($this->tenant, fn () => app(ChangeComplaintStatus::class)($complaint, ComplaintStatus::Done));
        $this->assertCount(2, $this->logs());
        $this->assertStringContainsString('Selesai', $this->logs()->last()->body);
    }

    public function test_the_monthly_generator_messages_every_new_invoice_once_and_a_rerun_adds_none(): void
    {
        $this->leaseFor();
        $this->leaseFor();
        $this->leaseFor();

        $this->generate();
        $this->assertCount(3, $this->logs());

        $this->generate();
        $this->assertCount(3, $this->logs());
        $this->assertSame(3, $this->usage());
    }

    public function test_a_broken_template_never_breaks_the_billing_that_triggered_it(): void
    {
        // Isi yang melewati validasi (mis. data lama) dengan variabel yang kini tidak diizinkan.
        $this->asTenant($this->tenant, fn () => MessageTemplate::create([
            'key' => 'invoice_issued', 'channel' => 'whatsapp', 'name' => 'Rusak', 'body' => 'Halo {{nomor_ktp}}', 'is_active' => true,
        ]));

        Log::spy();

        $invoice = $this->openInvoice();

        $this->assertNotNull($invoice->id);
        $this->assertCount(0, $this->logs());

        // Yang dicatat hanya nama kelas galat dan id, tanpa nomor, nama, atau isi pesan.
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($invoice) {
            $resident = $this->residentOf($invoice);

            return $context['exception'] === InvalidArgumentException::class
                && $context['invoice_id'] === $invoice->id
                && ! str_contains(json_encode($context), $resident->phone)
                && ! str_contains(json_encode($context), $resident->name);
        });
    }

    // ---- Pengingat tagihan ----

    public function test_reminders_follow_the_configured_offsets_and_only_those(): void
    {
        $beforeDue = $this->invoiceDueDaysAgo(-3);   // jatuh tempo 3 hari lagi: offset -3
        $dueToday = $this->invoiceDueDaysAgo(0);      // hari ini: offset 0
        $overdue = $this->invoiceDueDaysAgo(3);       // lewat 3 hari: offset +3
        $this->invoiceDueDaysAgo(-2);                  // tidak ada offset -2
        $this->invoiceDueDaysAgo(5);                   // tidak ada offset +5
        $this->clearMessages();

        $this->assertSame(3, $this->runReminders());

        $this->assertEquals([
            'reminder_before_due' => [$beforeDue->id],
            'reminder_due_today' => [$dueToday->id],
            'reminder_overdue' => [$overdue->id],
        ], $this->remindedByKey());
        $this->assertCount(3, $this->provider()->sent());
    }

    public function test_running_the_reminders_twice_never_sends_twice(): void
    {
        $this->invoiceDueDaysAgo(-3);
        $this->invoiceDueDaysAgo(3);
        $this->clearMessages();

        $this->runReminders();
        $this->assertSame(0, $this->runReminders());
        $this->artisan('messages:reminders')->assertSuccessful();
        $this->artisan('messages:reminders')->assertSuccessful();

        $this->assertCount(2, $this->logs());
        $this->assertCount(2, $this->provider()->sent());
    }

    public function test_the_next_day_brings_new_matches_but_not_repeats(): void
    {
        $invoice = $this->invoiceDueDaysAgo(-3);
        $this->clearMessages();
        $this->runReminders();

        $this->travelTo(CarbonImmutable::parse('2026-10-18 03:00:00', 'UTC')); // hari jatuh tempo
        $this->assertSame(1, $this->runReminders());

        $keys = $this->logs()->map(fn (MessageLog $log) => $log->template->key)->all();
        $this->assertSame(['reminder_before_due', 'reminder_due_today'], $keys);
        $this->assertSame([$invoice->id, $invoice->id], $this->logs()->pluck('subject_id')->all());
    }

    public function test_paid_void_and_partly_paid_invoices(): void
    {
        $paid = $this->invoiceDueDaysAgo(-3);
        $partial = $this->invoiceDueDaysAgo(-3);
        $void = $this->invoiceDueDaysAgo(-3);
        $this->record([[$paid, 1_500_000]]);
        $this->record([[$partial, 500_000]]);
        $this->asTenant($this->tenant, fn () => Invoice::query()->whereKey($void->id)->update(['status' => 'void']));
        $this->clearMessages();

        $this->assertSame(1, $this->runReminders());

        $log = $this->logs()->sole();
        $this->assertSame($partial->id, $log->subject_id);
        $this->assertStringContainsString('Rp 1.000.000', $log->body, 'Yang diingatkan adalah sisa tagihan.');
    }

    public function test_custom_offsets_replace_the_defaults(): void
    {
        $this->asTenant($this->tenant, fn () => BillingSetting::current()->update(['reminder_offsets' => [-1, 5]]));
        $tomorrow = $this->invoiceDueDaysAgo(-1);
        $late = $this->invoiceDueDaysAgo(5);
        $this->invoiceDueDaysAgo(-3);
        $this->invoiceDueDaysAgo(3);
        $this->clearMessages();

        $this->assertSame(2, $this->runReminders());

        $this->assertEquals(['reminder_before_due' => [$tomorrow->id], 'reminder_overdue' => [$late->id]], $this->remindedByKey());
    }

    public function test_reminders_for_opted_out_residents_are_logged_as_skipped_and_not_repeated(): void
    {
        $invoice = $this->invoiceDueDaysAgo(-3);
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($invoice->resident_id)->update(['whatsapp_opt_out_at' => now()]));
        $this->clearMessages();

        $this->runReminders();
        $this->runReminders();

        $log = $this->logs()->sole();
        $this->assertSame([MessageStatus::Skipped, MessageSkipReason::OptedOut], [$log->status, $log->skip_reason]);
        $this->assertSame([], $this->provider()->sent());
    }

    public function test_reminders_created_outside_the_send_window_wait_for_the_morning(): void
    {
        $this->invoiceDueDaysAgo(-3);
        $this->clearMessages();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 15:30:00', 'UTC')); // 23:30 WITA

        $this->runReminders();

        $log = $this->logs()->sole();
        $this->assertSame(MessageStatus::Queued, $log->status);
        $this->assertTrue($log->scheduled_for->equalTo(CarbonImmutable::parse('2026-10-16 00:00:00', 'UTC')));
        $this->assertSame([], $this->provider()->sent());
    }

    // ---- Kontrak segera habis ----

    public function test_lease_expiry_reminders_go_out_at_30_14_and_7_days_only(): void
    {
        $today = $this->tenant->today();
        $at30 = $this->leaseFor(['end_date' => $today->addDays(30)->toDateString()]);
        $at14 = $this->leaseFor(['end_date' => $today->addDays(14)->toDateString()]);
        $at7 = $this->leaseFor(['end_date' => $today->addDays(7)->toDateString()]);
        $this->leaseFor(['end_date' => $today->addDays(20)->toDateString()]);
        $this->leaseFor(['end_date' => $today->addDays(31)->toDateString()]);
        $this->clearMessages();

        $run = fn () => $this->asTenant($this->tenant, fn () => app(QueueLeaseExpiringReminders::class)());

        $this->assertSame(3, $run());
        $this->assertSame(0, $run());

        $logs = $this->logs();
        $this->assertEqualsCanonicalizing([$at30->id, $at14->id, $at7->id], $logs->pluck('subject_id')->all());
        $this->assertSame(['lease_expiring'], $logs->map(fn (MessageLog $log) => $log->template->key)->unique()->values()->all());
        $this->assertSame('lease', $logs->first()->subject_type);
    }

    public function test_ended_leases_are_not_reminded(): void
    {
        $lease = $this->leaseFor(['end_date' => $this->tenant->today()->addDays(7)->toDateString()]);
        $this->asTenant($this->tenant, fn () => $lease->update(['status' => 'ended', 'active_room_id' => null]));
        $this->clearMessages();

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => app(QueueLeaseExpiringReminders::class)()));
    }

    // ---- Penjadwal dan isolasi ----

    public function test_the_scheduler_command_queues_one_job_per_active_or_trial_tenant_only(): void
    {
        Queue::fake();
        [$trial] = $this->tenantWithOwner('business');
        [$suspended] = $this->tenantWithOwner('business');
        $trial->update(['status' => TenantStatus::Trial]);
        $suspended->update(['status' => TenantStatus::Suspended]);
        $this->tenant->update(['status' => TenantStatus::Active]);

        $this->artisan('messages:reminders')->assertSuccessful();

        Queue::assertPushed(SendTenantReminders::class, fn ($job) => in_array($job->tenantId, [$this->tenant->id, $trial->id], true));
        Queue::assertNotPushed(SendTenantReminders::class, fn ($job) => $job->tenantId === $suspended->id);
        Queue::assertPushed(SendTenantReminders::class, 2);
    }

    public function test_the_reminder_job_is_set_up_per_tenant_without_a_logged_in_user(): void
    {
        $this->invoiceDueDaysAgo(-3);
        $this->clearMessages();
        app(TenantContext::class)->forget();

        (new SendTenantReminders($this->tenant->id))->handle(
            app(TenantContext::class),
            app(QueueDueReminders::class),
            app(QueueLeaseExpiringReminders::class),
        );

        $this->assertCount(1, $this->logs());
    }

    public function test_the_reminder_job_skips_a_suspended_tenant(): void
    {
        $this->invoiceDueDaysAgo(-3);
        $this->clearMessages();
        $this->tenant->update(['status' => TenantStatus::Suspended]);

        (new SendTenantReminders($this->tenant->id))->handle(
            app(TenantContext::class),
            app(QueueDueReminders::class),
            app(QueueLeaseExpiringReminders::class),
        );

        $this->assertCount(0, $this->logs());
    }

    public function test_reminders_never_cross_tenants(): void
    {
        $mine = $this->invoiceDueDaysAgo(-3);
        [$other] = $this->tenantWithOwner('business');
        $lease = $this->leaseFor([], $other);
        $this->generate(null, $other);
        $theirs = $this->asTenant($other, fn () => Invoice::query()->where('lease_id', $lease->id)->firstOrFail());
        $this->asTenant($other, fn () => Invoice::query()->whereKey($theirs->id)->update(['due_date' => $other->today()->addDays(3)->toDateString()]));
        $this->clearMessages();

        $this->assertSame(1, $this->runReminders());

        $this->assertSame([$mine->id], $this->logs()->pluck('subject_id')->all());
        $this->assertCount(0, $this->logs($other));

        $this->assertSame(1, $this->asTenant($other, fn () => app(QueueDueReminders::class)()));
        $this->assertSame([$theirs->id], $this->logs($other)->pluck('subject_id')->all());
        $this->assertCount(1, $this->logs());
    }

    public function test_the_reminder_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'messages:reminders'));

        $this->assertCount(1, $events);
        $this->assertSame('0 2 * * *', $events->first()->expression);
    }
}
