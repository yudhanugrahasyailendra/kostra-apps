<?php

namespace Tests\Feature\Hardening;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\SubscriptionInvoice;
use App\Services\Demo\DemoTenantBuilder;
use App\Tenancy\TenantContext;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    private const COMMANDS = ['invoices:generate', 'invoices:mark-overdue', 'reminders:send', 'subscriptions:process'];

    /** @return array<string, int> */
    private function counts(): array
    {
        return TenantContext::instance()->unscoped(fn () => [
            'invoices' => Invoice::query()->count(),
            'payments' => Payment::query()->count(),
            'messages' => MessageLog::query()->count(),
            'subscription_invoices' => SubscriptionInvoice::query()->count(),
        ]);
    }

    public function test_menjalankan_seluruh_penjadwal_dua_kali_berturut_turut_tidak_menggandakan_apa_pun(): void
    {
        $this->seed([PlanSeeder::class, MessageTemplateSeeder::class]);
        app(DemoTenantBuilder::class)->build(['rooms' => 10, 'months' => 2, 'email' => 'jadwal@kostra.test', 'password' => 'rahasia-jadwal-1']);

        foreach (self::COMMANDS as $command) {
            $this->artisan($command)->assertSuccessful();
        }
        $afterFirst = $this->counts();

        foreach (self::COMMANDS as $command) {
            $this->artisan($command)->assertSuccessful();
        }

        $this->assertSame($afterFirst, $this->counts());
    }

    public function test_semua_perintah_terjadwal_berjalan_satu_server_tanpa_tumpang_tindih(): void
    {
        $events = collect(Schedule::events())->filter(fn ($e) => collect(self::COMMANDS)->contains(fn ($c) => str_contains($e->command, $c)));

        $this->assertCount(count(self::COMMANDS), $events);
        $events->each(function ($event) {
            $this->assertTrue($event->onOneServer, $event->command.' harus onOneServer.');
            $this->assertTrue($event->withoutOverlapping, $event->command.' harus withoutOverlapping.');
        });
    }

    public function test_pekerjaan_gagal_dan_antrean_menumpuk_tercatat_tanpa_payload(): void
    {
        Log::spy();

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\Jobs\SendWhatsAppMessage');
        $job->shouldReceive('getQueue')->andReturn('default');

        Event::dispatch(new JobFailed('database', $job, new \RuntimeException('isi pesan: halo +6281234567890')));
        Event::dispatch(new QueueBusy('database', 'default', 150));

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Pekerjaan antrean gagal'
            && $context['exception'] === \RuntimeException::class
            && ! str_contains(json_encode($context), '6281234567890'))->once();
        Log::shouldHaveReceived('critical')->withArgs(fn ($message, $context) => $message === 'Antrean menumpuk' && $context['size'] === 150)->once();
    }
}
