<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Actions\Billing\MarkOverdueInvoices;
use App\Enums\TenantStatus;
use App\Jobs\RunTenantBilling;
use App\Models\BillingSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BillingFixtures;
use Tests\TestCase;

class BillingSettingsAndSchedulerTest extends TestCase
{
    use BillingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    // ---- Pengaturan ----

    public function test_registration_creates_billing_settings_with_defaults(): void
    {
        $this->post(route('register'), [
            'business_name' => 'Kos Baru', 'name' => 'Bu Sari', 'email' => 'baru@example.com',
            'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123',
        ])->assertRedirect('/owner');

        $settings = $this->withoutTenant(fn () => BillingSetting::whereHas('tenant', fn ($q) => $q->where('name', 'Kos Baru'))->sole());
        $this->assertSame('INV', $settings->invoice_prefix);
        $this->assertSame([1, 10, false, false], [$settings->issue_day, $settings->due_day, $settings->prorate_first_month, $settings->prorate_last_month]);
        $this->assertSame([-3, 0, 3], $settings->reminder_offsets);
    }

    public function test_settings_are_created_on_demand_for_older_tenants(): void
    {
        $this->assertSame(0, $this->withoutTenant(fn () => BillingSetting::count()));

        $this->actingAs($this->owner)->get(route('owner.settings.billing'))->assertOk()->assertSee('INV');

        $this->assertSame(1, $this->withoutTenant(fn () => BillingSetting::count()));
        $this->actingAs($this->owner)->get(route('owner.settings.billing'))->assertOk();
        $this->assertSame(1, $this->withoutTenant(fn () => BillingSetting::count()), 'Tidak boleh membuat baris kedua.');
    }

    public function test_owner_updates_settings_and_they_apply_to_the_next_generation(): void
    {
        $this->actingAs($this->owner)->put(route('owner.settings.billing.update'), [
            'invoice_prefix' => 'kos', 'issue_day' => 5, 'due_day' => 20, 'prorate_first_month' => '1', 'prorate_last_month' => '0',
        ])->assertRedirect(route('owner.settings.billing'));

        $settings = $this->asTenant($this->tenant, fn () => BillingSetting::current());
        $this->assertSame(['KOS', 5, 20, true, false], [$settings->invoice_prefix, $settings->issue_day, $settings->due_day, $settings->prorate_first_month, $settings->prorate_last_month]);

        $this->leaseFor(['start_date' => '2026-10-10', 'end_date' => null]);
        $this->generate();

        $invoice = $this->invoices()->sole();
        $this->assertSame('KOS-2026-10-0001', $invoice->number);
        // Pro rata bulan pertama: 22 dari 31 hari (10 sampai 31 Okt) = Rp 1.064.516; jatuh tempo tanggal 20.
        $this->assertSame(1_064_516, $invoice->total);
        $this->assertSame('2026-10-20', $invoice->due_date->toDateString());
    }

    public function test_settings_validation(): void
    {
        $put = fn (array $o) => $this->actingAs($this->owner)->put(route('owner.settings.billing.update'), array_merge([
            'invoice_prefix' => 'INV', 'issue_day' => 1, 'due_day' => 10,
        ], $o));

        $put(['invoice_prefix' => 'I'])->assertSessionHasErrors('invoice_prefix');
        $put(['invoice_prefix' => 'INV-1'])->assertSessionHasErrors('invoice_prefix');
        $put(['invoice_prefix' => 'TERLALUPANJANG'])->assertSessionHasErrors('invoice_prefix');
        $put(['issue_day' => 0])->assertSessionHasErrors('issue_day');
        $put(['issue_day' => 29])->assertSessionHasErrors('issue_day');
        $put(['due_day' => 31])->assertSessionHasErrors('due_day');
        $put(['due_day' => 'x'])->assertSessionHasErrors('due_day');
    }

    public function test_only_users_with_the_settings_permission_can_open_billing_settings(): void
    {
        $plain = User::factory()->for($this->tenant)->staff()->create();
        $allowed = User::factory()->for($this->tenant)->staff(['settings.manage'])->create();

        $this->actingAs($plain)->get(route('owner.settings.billing'))->assertForbidden();
        $this->actingAs($plain)->put(route('owner.settings.billing.update'), ['invoice_prefix' => 'INV', 'issue_day' => 1, 'due_day' => 2])->assertForbidden();
        $this->actingAs($allowed)->get(route('owner.settings.billing'))->assertOk();
        $this->actingAs($allowed)->get(route('owner.settings'))->assertOk()->assertSee('Penagihan');
    }

    public function test_settings_are_isolated_between_tenants(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $this->asTenant($other, fn () => BillingSetting::current()->update(['invoice_prefix' => 'ZZZ']));

        $this->actingAs($this->owner)->put(route('owner.settings.billing.update'), ['invoice_prefix' => 'AAA', 'issue_day' => 1, 'due_day' => 10]);

        $this->assertSame('ZZZ', $this->asTenant($other, fn () => BillingSetting::current()->invoice_prefix));
        $this->assertSame('AAA', $this->asTenant($this->tenant, fn () => BillingSetting::current()->invoice_prefix));
    }

    // ---- Penjadwal ----

    private function runJob(Tenant $tenant): void
    {
        (new RunTenantBilling($tenant->id))->handle(app(TenantContext::class), app(GenerateMonthlyInvoices::class), app(MarkOverdueInvoices::class));
    }

    public function test_the_job_bills_one_tenant_in_its_own_context_and_marks_overdue(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $this->leaseFor();
        $this->leaseFor([], $other);

        $this->runJob($this->tenant);

        $this->assertSame(1, $this->invoices()->count());
        $this->assertSame(0, $this->invoices($other)->count());
        $this->assertNull(app(TenantContext::class)->id(), 'Konteks tenant harus dipulihkan setelah job.');

        $this->travelTo('2026-10-20 03:00:00');
        $this->runJob($this->tenant);
        $this->assertSame('overdue', $this->invoices()->sole()->status->value);
        $this->assertSame(1, $this->invoices()->count(), 'Dijalankan ulang tidak menambah tagihan.');
    }

    public function test_suspended_and_closed_tenants_are_not_billed(): void
    {
        $suspended = Tenant::factory()->suspended()->create(['plan_id' => $this->tenant->plan_id]);
        $closed = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id, 'status' => TenantStatus::Closed]);
        $this->leaseFor([], $suspended);
        $this->leaseFor([], $closed);

        $this->runJob($suspended);
        $this->runJob($closed);

        $this->assertSame(0, $this->invoices($suspended)->count());
        $this->assertSame(0, $this->invoices($closed)->count());
    }

    public function test_a_missing_tenant_is_ignored(): void
    {
        (new RunTenantBilling(999_999))->handle(app(TenantContext::class), app(GenerateMonthlyInvoices::class), app(MarkOverdueInvoices::class));

        $this->assertTrue(true);
    }

    public function test_billing_run_queues_one_unique_job_per_eligible_tenant(): void
    {
        Queue::fake();
        $trial = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id, 'status' => TenantStatus::Trial]);
        Tenant::factory()->suspended()->create(['plan_id' => $this->tenant->plan_id]);
        Tenant::factory()->create(['plan_id' => $this->tenant->plan_id, 'status' => TenantStatus::Closed]);

        $this->artisan('billing:run')->assertSuccessful();

        Queue::assertPushed(RunTenantBilling::class, 2);
        Queue::assertPushed(RunTenantBilling::class, fn (RunTenantBilling $job) => $job->tenantId === $this->tenant->id);
        Queue::assertPushed(RunTenantBilling::class, fn (RunTenantBilling $job) => $job->tenantId === $trial->id);
        $this->assertSame((string) $this->tenant->id, (new RunTenantBilling($this->tenant->id))->uniqueId());
    }

    public function test_billing_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'billing:run'));

        $this->assertNotNull($event, 'billing:run belum dijadwalkan.');
        $this->assertSame('0 1 * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_the_generate_command_backfills_a_period_idempotently(): void
    {
        $this->leaseFor(['start_date' => '2026-08-01']);

        $this->artisan('billing:generate', ['tenant' => $this->tenant->id, '--period' => '2026-09'])
            ->expectsOutputToContain('Terbit: 1')->assertSuccessful();
        $this->artisan('billing:generate', ['tenant' => $this->tenant->id, '--period' => '2026-09'])
            ->expectsOutputToContain('Terbit: 0, dilewati: 1')->assertSuccessful();

        $this->assertSame(['2026-09'], $this->invoices()->pluck('period')->all());

        $this->artisan('billing:generate', ['tenant' => $this->tenant->id, '--period' => '2026-9'])->assertFailed();
        $this->artisan('billing:generate', ['tenant' => 999999])->assertFailed();
    }
}
