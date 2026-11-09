<?php

namespace Tests\Feature\Messaging;

use App\Actions\ApplyProviderStatus;
use App\Actions\ChangeComplaintStatus;
use App\Actions\CreateComplaint;
use App\Actions\DeliverWhatsAppMessage;
use App\Actions\GenerateMonthlyInvoices;
use App\Actions\QueueWhatsAppMessage;
use App\Actions\RecordPayment;
use App\Actions\ResendWhatsAppMessage;
use App\Actions\SaveMessageTemplate;
use App\Actions\SendInvoiceReminders;
use App\Enums\ComplaintStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PaymentMethod;
use App\Jobs\SendWhatsAppMessage;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Services\WhatsApp\MessageQuota;
use App\Services\WhatsApp\MessageTemplateRenderer;
use App\Services\WhatsApp\SendWindow;
use App\Services\WhatsApp\StatusUpdate;
use App\Services\WhatsApp\WhatsAppProvider;
use App\Tenancy\TenantContext;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MessagingDomainTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private Property $propertyA;

    private Room $roomA;

    private Resident $residentA;

    private Resident $residentB;

    private FakeWhatsAppProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();

        // 04:00 UTC = 12:00 Asia/Makassar: di dalam jam kirim bawaan 08:00-20:00.
        Carbon::setTestNow('2026-11-09 04:00:00');
        $this->seed(MessageTemplateSeeder::class);

        $this->tenantA = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 5])->id]);
        $this->tenantB = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 5])->id]);
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();

        $this->inA(function () {
            $this->propertyA = Property::factory()->create();
            $this->roomA = Room::factory()->create(['property_id' => $this->propertyA->id]);
            $this->residentA = Resident::factory()->create(['name' => 'Budi']);
        });
        $this->residentB = $this->inB(fn () => Resident::factory()->create(['name' => 'Siti']));

        $this->fake = app(WhatsAppProvider::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inA(callable $callback): mixed
    {
        return TenantContext::instance()->runAs($this->tenantA, $callback);
    }

    private function inB(callable $callback): mixed
    {
        return TenantContext::instance()->runAs($this->tenantB, $callback);
    }

    /** Log hasil antrean; dengan antrean sync pengiriman sudah selesai, jadi dibaca ulang dari basis data. */
    private function queueFor(Resident $resident, MessageTemplateKey $key = MessageTemplateKey::InvoiceIssued): ?MessageLog
    {
        $log = $this->inA(fn () => app(QueueWhatsAppMessage::class)->handle($key, $resident, [
            'jumlah' => 'Rp 1.500.000', 'jatuh_tempo' => '14 November 2026', 'link' => '',
        ]));

        return $log === null ? null : $this->inA(fn () => $log->fresh());
    }

    private function invoiceFor(Resident $resident, int $dueInDays, InvoiceStatus $status = InvoiceStatus::Issued): Invoice
    {
        return $this->inA(function () use ($resident, $dueInDays, $status) {
            // Satu kontrak aktif per kamar: tiap tagihan memakai kamar sendiri.
            $room = Room::factory()->create(['property_id' => $this->propertyA->id]);
            $lease = Lease::factory()->create([
                'property_id' => $this->propertyA->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id,
            ]);

            return Invoice::factory()->create([
                'property_id' => $this->propertyA->id, 'lease_id' => $lease->id, 'resident_id' => $resident->id,
                'due_date' => now()->addDays($dueInDays)->toDateString(), 'status' => $status,
                'paid_total' => $status === InvoiceStatus::Paid ? 1500000 : 0,
            ]);
        });
    }

    // Pengiriman dasar

    public function test_pesan_dirender_dicatat_dan_terkirim_lewat_penyedia_palsu(): void
    {
        $log = $this->queueFor($this->residentA);

        $this->assertSame(MessageStatus::Sent, $log->status);
        $this->assertSame('fake', $log->provider);
        $this->assertSame('utility', $log->category);
        $this->assertTrue($log->counted_in_quota);
        $this->assertNotNull($log->provider_message_id);
        $this->assertStringContainsString('Halo Budi', $log->body);
        $this->assertStringContainsString('Rp 1.500.000', $log->body);
        $this->assertStringNotContainsString('{{', $log->body);
        $this->assertCount(1, $this->fake->sent());
        $this->assertSame($this->residentA->phone, $this->fake->sent()[0]->recipient);
        $this->assertSame(1, app(MessageQuota::class)->used($this->tenantA));
    }

    // Opt-out

    public function test_penghuni_opt_out_dicatat_skipped_dan_tidak_dikirim(): void
    {
        $this->inA(fn () => $this->residentA->update(['whatsapp_opt_out_at' => now()]));

        $log = $this->queueFor($this->residentA->fresh());

        $this->assertSame(MessageStatus::Skipped, $log->status);
        $this->assertSame(MessageSkipReason::OptedOut, $log->skip_reason);
        $this->assertFalse($log->counted_in_quota);
        $this->assertSame([], $this->fake->sent());
        $this->assertSame(0, app(MessageQuota::class)->used($this->tenantA));
    }

    public function test_opt_out_setelah_diantrekan_tetap_menghentikan_pengiriman(): void
    {
        Queue::fake();
        $log = $this->queueFor($this->residentA);
        $this->inA(fn () => $this->residentA->update(['whatsapp_opt_out_at' => now()]));

        [$outcome] = $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($log));

        $this->assertSame(DeliverWhatsAppMessage::SKIPPED, $outcome);
        $this->assertSame(MessageSkipReason::OptedOut, $this->inA(fn () => $log->fresh())->skip_reason);
        $this->assertSame([], $this->fake->sent());
    }

    // Kuota

    public function test_kuota_habis_menghasilkan_skipped_quota_exceeded(): void
    {
        $this->inA(fn () => UsageCounter::factory()->create(['used' => 5]));

        $log = $this->queueFor($this->residentA);

        $this->assertSame(MessageStatus::Skipped, $log->status);
        $this->assertSame(MessageSkipReason::QuotaExceeded, $log->skip_reason);
        $this->assertSame([], $this->fake->sent());
        $this->assertSame(5, app(MessageQuota::class)->used($this->tenantA));
    }

    public function test_paket_tanpa_kuota_tidak_mengirim(): void
    {
        $this->tenantA->plan->update(['monthly_message_quota' => 0]);

        $log = $this->queueFor($this->residentA);

        $this->assertSame(MessageSkipReason::QuotaExceeded, $log->skip_reason);
        $this->assertSame([], $this->fake->sent());
    }

    public function test_dua_puluh_job_dengan_sisa_kuota_lima_mengirim_tepat_lima(): void
    {
        Queue::fake();
        $logs = [];
        for ($i = 0; $i < 20; $i++) {
            $logs[] = $this->queueFor($this->residentA);
        }
        Queue::assertPushed(SendWhatsAppMessage::class, 20);

        // Tiap job membaca keadaan awal yang sama dan berebut penghitung lewat UPDATE bersyarat.
        foreach ($logs as $log) {
            (new SendWhatsAppMessage($this->tenantA->id, $log->id))->handle(app(DeliverWhatsAppMessage::class));
        }

        $statuses = $this->inA(fn () => MessageLog::query()->pluck('status')->countBy(fn ($s) => $s->value)->all());
        $this->assertSame(['sent' => 5, 'skipped' => 15], $statuses);
        $this->assertCount(5, $this->fake->sent());
        $this->assertSame(5, app(MessageQuota::class)->used($this->tenantA));
    }

    public function test_reserve_bersyarat_tidak_melewati_batas_walau_pembaca_memegang_nilai_lama(): void
    {
        $quota = app(MessageQuota::class);
        $stale = $this->inA(fn () => UsageCounter::factory()->create(['used' => 4]));

        $this->assertTrue($quota->reserve($this->tenantA));
        // $stale masih mengira used = 4; basis data sudah 5.
        $this->assertSame(4, $stale->used);
        $this->assertFalse($quota->reserve($this->tenantA));
        $this->assertSame(5, $quota->used($this->tenantA));
    }

    public function test_kuota_bulanan_terpisah_per_periode(): void
    {
        $this->inA(fn () => UsageCounter::factory()->create(['period' => '2026-10', 'used' => 5]));

        $this->assertSame(5, app(MessageQuota::class)->remaining($this->tenantA));
        $this->assertSame(MessageStatus::Sent, $this->queueFor($this->residentA)->status);
    }

    // Jam kirim

    public function test_pesan_di_luar_jam_kirim_ditunda_ke_jam_berikutnya(): void
    {
        Queue::fake();
        // 16:00 UTC = 00:00 Asia/Makassar, di luar 08:00-20:00.
        Carbon::setTestNow('2026-11-09 16:00:00');

        $log = $this->queueFor($this->residentA);

        $opening = Carbon::parse('2026-11-10 00:00:00', 'UTC'); // 08:00 Makassar
        $this->assertSame(MessageStatus::Queued, $log->status);
        $this->assertTrue($opening->equalTo($log->scheduled_for));
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->delay?->equalTo($opening) ?? false);

        // Dijalankan sebelum jam buka: ditunda lagi, tidak dikirim, tidak memakai kuota.
        [$outcome, $until] = $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($log));
        $this->assertSame(DeliverWhatsAppMessage::DEFERRED, $outcome);
        $this->assertTrue($opening->equalTo($until));
        $this->assertSame([], $this->fake->sent());
        $this->assertSame(0, app(MessageQuota::class)->used($this->tenantA));

        // Setelah jam buka: terkirim.
        Carbon::setTestNow($opening);
        [$outcome] = $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($log));
        $this->assertSame(DeliverWhatsAppMessage::SENT, $outcome);
        $this->assertCount(1, $this->fake->sent());
    }

    public function test_jendela_kirim_melewati_tengah_malam_dan_sepanjang_hari(): void
    {
        $window = app(SendWindow::class);
        $overnight = new BillingSetting(['send_window_start' => '20:00:00', 'send_window_end' => '06:00:00']);
        $allDay = new BillingSetting(['send_window_start' => '00:00:00', 'send_window_end' => '00:00:00']);

        // 12:00 Makassar: di luar jendela malam, dibuka 20:00 Makassar (12:00 UTC).
        $this->assertTrue(Carbon::parse('2026-11-09 12:00:00', 'UTC')->equalTo($window->nextOpening($this->tenantA, $overnight, now())));
        // 23:00 Makassar: di dalam jendela malam.
        $this->assertNull($window->nextOpening($this->tenantA, $overnight, Carbon::parse('2026-11-09 15:00:00', 'UTC')));
        $this->assertNull($window->nextOpening($this->tenantA, $allDay, Carbon::parse('2026-11-09 18:00:00', 'UTC')));
    }

    // Percobaan ulang

    public function test_kegagalan_dicoba_ulang_sampai_batas_lalu_failed_dengan_kode_galat(): void
    {
        Queue::fake();
        $this->fake->failNext(3, 'RATE_LIMIT');
        $log = $this->queueFor($this->residentA);
        $deliver = app(DeliverWhatsAppMessage::class);

        [$first, $at] = $this->inA(fn () => $deliver->handle($log));
        $this->assertSame(DeliverWhatsAppMessage::RETRY, $first);
        $this->assertTrue(now()->addSeconds(60)->equalTo($at));
        [$second, $at] = $this->inA(fn () => $deliver->handle($log));
        $this->assertSame(DeliverWhatsAppMessage::RETRY, $second);
        $this->assertTrue(now()->addSeconds(300)->equalTo($at));
        [$third] = $this->inA(fn () => $deliver->handle($log));
        $this->assertSame(DeliverWhatsAppMessage::FAILED, $third);

        $fresh = $this->inA(fn () => $log->fresh());
        $this->assertSame(MessageStatus::Failed, $fresh->status);
        $this->assertSame('RATE_LIMIT', $fresh->error_code);
        $this->assertSame(3, $fresh->attempts);
        $this->assertNotNull($fresh->failed_at);
        // Jatah kuota pesan yang gagal dikembalikan.
        $this->assertFalse($fresh->counted_in_quota);
        $this->assertSame(0, app(MessageQuota::class)->used($this->tenantA));
        $this->assertSame([], $this->fake->sent());
    }

    public function test_kegagalan_permanen_langsung_failed_dan_percobaan_berikutnya_berhasil_tidak_memakai_kuota_dua_kali(): void
    {
        Queue::fake();
        $this->fake->failNext(1, 'INVALID_NUMBER', retryable: false);
        $permanent = $this->queueFor($this->residentA);
        [$outcome] = $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($permanent));
        $this->assertSame(DeliverWhatsAppMessage::FAILED, $outcome);
        $this->assertSame(1, $this->inA(fn () => $permanent->fresh())->attempts);

        $this->fake->failNext(1, 'TIMEOUT');
        $retry = $this->queueFor($this->residentA);
        $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($retry));
        $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($retry));

        $this->assertSame(MessageStatus::Sent, $this->inA(fn () => $retry->fresh())->status);
        $this->assertSame(1, app(MessageQuota::class)->used($this->tenantA));
    }

    public function test_job_mengirim_ulang_lewat_release_saat_gagal_sementara(): void
    {
        $this->fake->failNext(1, 'TIMEOUT');

        $log = $this->queueFor($this->residentA); // antrean sync: job berjalan langsung

        $fresh = $this->inA(fn () => $log->fresh());
        $this->assertSame(MessageStatus::Queued, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertSame('TIMEOUT', $fresh->error_code);
    }

    public function test_pesan_gagal_dapat_dikirim_ulang_dari_log(): void
    {
        Queue::fake();
        $this->fake->failNext(1, 'INVALID_NUMBER', retryable: false);
        $log = $this->queueFor($this->residentA);
        $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($log));

        $this->inA(fn () => app(ResendWhatsAppMessage::class)->handle($log));
        $fresh = $this->inA(fn () => $log->fresh());
        $this->assertSame(MessageStatus::Queued, $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        $this->assertNull($fresh->error_code);

        $this->inA(fn () => app(DeliverWhatsAppMessage::class)->handle($log));
        $this->assertSame(MessageStatus::Sent, $this->inA(fn () => $log->fresh())->status);

        $this->expectException(ValidationException::class);
        $this->inA(fn () => app(ResendWhatsAppMessage::class)->handle($log));
    }

    public function test_status_webhook_mengubah_sent_menjadi_delivered_atau_failed(): void
    {
        $log = $this->queueFor($this->residentA);
        $update = $this->fake->parseStatusWebhook(['message_id' => $log->fresh()->provider_message_id, 'status' => 'delivered']);
        $this->assertInstanceOf(StatusUpdate::class, $update);

        app(ApplyProviderStatus::class)->handle($update);

        $fresh = $this->inA(fn () => $log->fresh());
        $this->assertSame(MessageStatus::Delivered, $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNull($this->fake->parseStatusWebhook(['message_id' => 'x', 'status' => 'read']));
        $this->assertNull(app(ApplyProviderStatus::class)->handle(new StatusUpdate('tidak-ada', MessageStatus::Delivered)));
    }

    // Penjadwal pengingat

    public function test_penjadwal_dua_kali_tidak_menggandakan_pengingat_dan_tagihan_lunas_tidak_diingatkan(): void
    {
        $this->inA(fn () => BillingSetting::current()->update(['reminder_offsets' => [-3, 0, 3]]));
        $before = $this->invoiceFor($this->residentA, 3);             // H-3
        $today = $this->invoiceFor($this->residentA, 0);              // H-0
        $late = $this->invoiceFor($this->residentA, -3, InvoiceStatus::Overdue); // H+3
        $paid = $this->invoiceFor($this->residentA, 3, InvoiceStatus::Paid);
        $void = $this->invoiceFor($this->residentA, 0, InvoiceStatus::Void);
        $other = $this->invoiceFor($this->residentA, 10);             // bukan hari pengingat
        $reminders = app(SendInvoiceReminders::class);

        $this->assertSame(3, $reminders->handle($this->tenantA));
        $this->assertSame(0, $reminders->handle($this->tenantA));

        $logs = $this->inA(fn () => MessageLog::query()->orderBy('id')->get());
        $this->assertCount(3, $logs);
        $this->assertEqualsCanonicalizing(
            [$before->id, $today->id, $late->id],
            $logs->pluck('subject_id')->all(),
        );
        $this->assertSame(3, $this->inA(fn () => MessageLog::query()->where('subject_type', 'invoice')->count()));
        $this->assertStringContainsString('jatuh tempo pada', $logs->firstWhere('subject_id', $before->id)->body);
        $this->assertStringContainsString('hari ini', $logs->firstWhere('subject_id', $today->id)->body);
        $this->assertStringContainsString('melewati jatuh tempo', $logs->firstWhere('subject_id', $late->id)->body);
        $this->assertCount(3, $this->fake->sent());
    }

    public function test_pengingat_keesokan_harinya_dengan_offset_lain_tetap_terkirim(): void
    {
        $this->inA(fn () => BillingSetting::current()->update(['reminder_offsets' => [-3, -1]]));
        $this->invoiceFor($this->residentA, 3);
        $reminders = app(SendInvoiceReminders::class);

        $this->assertSame(1, $reminders->handle($this->tenantA));

        Carbon::setTestNow('2026-11-11 04:00:00'); // jatuh tempo tinggal 1 hari
        $this->assertSame(1, $reminders->handle($this->tenantA));
        $this->assertSame(0, $reminders->handle($this->tenantA));
        $this->assertSame(2, $this->inA(fn () => MessageLog::query()->count()));
    }

    public function test_perintah_reminders_send_berjalan_untuk_semua_tenant(): void
    {
        $this->inA(fn () => BillingSetting::current()->update(['reminder_offsets' => [0]]));
        $this->invoiceFor($this->residentA, 0);

        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertSame(1, $this->inA(fn () => MessageLog::query()->count()));
    }

    // Template

    public function test_renderer_menolak_variabel_tak_dikenal_dan_kurung_rusak(): void
    {
        $renderer = new MessageTemplateRenderer;

        $this->assertSame('Halo Budi, Rp 5', $renderer->render('Halo {{nama}}, {{ jumlah }}', ['nama' => 'Budi', 'jumlah' => 'Rp 5']));
        $this->assertSame(['alamat'], $renderer->unknownVariables('{{nama}} {{alamat}}'));

        foreach (['Halo {{alamat}}', 'Halo {{nama', 'Halo {{ nama }} }}'] as $bad) {
            try {
                $renderer->render($bad, ['nama' => 'Budi']);
                $this->fail("Seharusnya ditolak: $bad");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('body', $e->errors());
            }
        }
    }

    public function test_pemilik_menyunting_template_dalam_batas_variabel_dan_category_tetap_utility(): void
    {
        $this->inA(function () {
            $save = app(SaveMessageTemplate::class);

            $template = $save->handle(MessageTemplateKey::InvoiceIssued, 'Yth {{nama}}, tagihan {{jumlah}} jatuh tempo {{jatuh_tempo}}.');
            $this->assertFalse($template->isSystem());
            $this->assertSame('utility', $template->category);

            // Category tidak bisa diubah lewat model, baik saat membuat maupun mengubah.
            $template->category = 'marketing';
            $template->save();
            $this->assertSame('utility', $template->fresh()->category);
            $this->assertSame('utility', MessageTemplate::create(['key' => 'receipt', 'name' => 'x', 'body' => 'y', 'category' => 'marketing'])->category);

            try {
                $save->handle(MessageTemplateKey::InvoiceIssued, 'Halo {{alamat}}');
                $this->fail('Variabel tak dikenal seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('body', $e->errors());
            }
            $this->assertStringContainsString('Yth', MessageTemplate::effective(MessageTemplateKey::InvoiceIssued)->body);
        });

        // Template sistem tidak berubah.
        $system = TenantContext::instance()->unscoped(fn () => MessageTemplate::query()->whereNull('tenant_id')->where('key', 'invoice_issued')->sole());
        $this->assertStringStartsWith('Halo {{nama}}', $system->body);
    }

    public function test_template_kustom_dipakai_pesan_dan_reset_kembali_ke_bawaan(): void
    {
        $this->inA(fn () => app(SaveMessageTemplate::class)->handle(MessageTemplateKey::Receipt, 'Kuitansi untuk {{nama}}: {{jumlah}}'));
        $custom = $this->inA(fn () => app(QueueWhatsAppMessage::class)->handle(MessageTemplateKey::Receipt, $this->residentA, ['jumlah' => 'Rp 1']));
        $this->assertSame('Kuitansi untuk Budi: Rp 1', $custom->body);

        $this->inA(fn () => app(SaveMessageTemplate::class)->reset(MessageTemplateKey::Receipt));
        $default = $this->inA(fn () => app(QueueWhatsAppMessage::class)->handle(MessageTemplateKey::Receipt, $this->residentA, ['jumlah' => 'Rp 1']));
        $this->assertStringStartsWith('Halo Budi, pembayaran', $default->body);
    }

    public function test_seeder_template_sistem_idempoten_dan_lengkap(): void
    {
        $this->seed(MessageTemplateSeeder::class);

        $keys = TenantContext::instance()->unscoped(fn () => MessageTemplate::query()->whereNull('tenant_id')->orderBy('key')->pluck('key')->all());
        $this->assertSame(
            collect(MessageTemplateKey::cases())->map->value->sort()->values()->all(),
            $keys,
        );
        $renderer = new MessageTemplateRenderer;
        TenantContext::instance()->unscoped(fn () => MessageTemplate::query()->whereNull('tenant_id')->get()->each(function ($t) use ($renderer) {
            $renderer->validate($t->body);
            $this->assertSame('utility', $t->category);
        }));
    }

    // Listener event

    public function test_alur_tagihan_terbit_kuitansi_dan_komplain_tercatat_di_log_dengan_penyedia_palsu(): void
    {
        $this->inA(function () {
            $lease = Lease::factory()->create([
                'property_id' => $this->propertyA->id, 'room_id' => $this->roomA->id, 'primary_resident_id' => $this->residentA->id,
                'start_date' => '2026-11-01', 'end_date' => null,
            ]);
            BillingSetting::current();

            // Tagihan bulanan terbit -> pesan invoice_issued.
            app(GenerateMonthlyInvoices::class)->handle($this->tenantA, Carbon::parse('2026-11-09'));
            $invoice = Invoice::query()->where('lease_id', $lease->id)->sole();
            $this->assertSame(1, MessageLog::query()->where('subject_type', 'invoice')->count());

            // Pembayaran dicatat -> kuitansi.
            $payment = app(RecordPayment::class)->handle(
                $this->residentA, $invoice->total, now(), PaymentMethod::Transfer, [$invoice->id => $invoice->total], $this->ownerA,
            );
            $this->assertSame(1, MessageLog::query()->where('subject_type', 'payment')->where('subject_id', $payment->id)->count());

            // Status komplain berubah -> pemberitahuan.
            $complaint = app(CreateComplaint::class)->handle([
                'room_id' => $this->roomA->id, 'resident_id' => $this->residentA->id,
                'category' => 'water', 'title' => 'Air mati', 'description' => 'Tidak mengalir',
            ], $this->ownerA);
            app(ChangeComplaintStatus::class)->handle($complaint, ComplaintStatus::InProgress);
            $this->assertSame(1, MessageLog::query()->where('subject_type', 'complaint')->count());
        });

        // Tepat satu pesan per pemicu (listener tidak terdaftar ganda), semuanya terkirim lewat penyedia palsu.
        $logs = $this->inA(fn () => MessageLog::query()->orderBy('id')->get());
        $this->assertCount(3, $logs);
        $this->assertTrue($logs->every(fn ($l) => $l->status === MessageStatus::Sent && $l->provider === 'fake'));
        $this->assertCount(3, $this->fake->sent());
        $this->assertStringContainsString('telah terbit', $logs[0]->body);
        $this->assertStringContainsString('sudah kami terima', $logs[1]->body);
        $this->assertStringContainsString('komplain', $logs[2]->body);
    }

    public function test_tanpa_template_tidak_ada_pesan_dan_alur_bisnis_tetap_jalan(): void
    {
        TenantContext::instance()->unscoped(fn () => MessageTemplate::query()->delete());

        $this->assertNull($this->queueFor($this->residentA));
        $this->assertSame(0, $this->inA(fn () => MessageLog::query()->count()));
    }

    // Isolasi tenant

    public function test_isolasi_tenant_untuk_template_log_dan_penghitung(): void
    {
        $this->inA(fn () => app(SaveMessageTemplate::class)->handle(MessageTemplateKey::Receipt, 'Rahasia A {{nama}}'));
        $this->queueFor($this->residentA);

        $this->inB(function () {
            $this->assertSame(0, MessageLog::query()->count());
            $this->assertSame(0, UsageCounter::query()->count());
            $this->assertSame(0, MessageTemplate::query()->count()); // salinan A tidak terlihat; sistem butuh unscoped
            $this->assertStringStartsWith('Halo {{nama}}, pembayaran', MessageTemplate::effective(MessageTemplateKey::Receipt)->body);
            $this->assertSame(5, app(MessageQuota::class)->remaining($this->tenantB));
        });

        // Penghuni tenant lain tidak bisa dikirimi lewat konteks tenant ini.
        $this->assertNull($this->inA(fn () => app(QueueWhatsAppMessage::class)->handle(MessageTemplateKey::Receipt, $this->residentB)));
        $this->assertSame(1, $this->inA(fn () => MessageLog::query()->count()));
        $this->assertSame(1, app(MessageQuota::class)->used($this->tenantA));
        $this->assertSame(0, app(MessageQuota::class)->used($this->tenantB));
    }
}
