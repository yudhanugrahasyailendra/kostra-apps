<?php

namespace Tests\Feature\Subscriptions;

use App\Actions\Auth\RegisterOwner;
use App\Actions\Subscriptions\ActivateTenant;
use App\Actions\Subscriptions\CreateFreeSubscription;
use App\Actions\Subscriptions\DowngradePlan;
use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Actions\Subscriptions\RunSubscriptionLifecycle;
use App\Actions\Subscriptions\SetTenantPlan;
use App\Actions\Subscriptions\StartTrial;
use App\Actions\Subscriptions\SuspendTenant;
use App\Actions\Subscriptions\VoidSubscriptionInvoice;
use App\Enums\BillingPeriod;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Jobs\RunTenantSubscription;
use App\Models\Property;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plans\PlanFeatures;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SubscriptionFixtures;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase, SubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSubscriptions();
    }

    private function at(string $utc): void
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    }

    private function fresh(Tenant $tenant): Tenant
    {
        return Tenant::query()->findOrFail($tenant->getKey());
    }

    // ---- Pendaftaran dan batas Gratis ----

    public function test_registration_creates_a_free_subscription_and_the_free_limits_apply(): void
    {
        $owner = app(RegisterOwner::class)(['name' => 'Budi', 'business_name' => 'Kos Melati', 'email' => 'budi@example.test', 'password' => 'rahasia-1234']);
        $tenant = $owner->tenant;

        $subscription = $this->currentSubscription($tenant);
        $this->assertSame(['free', SubscriptionStatus::Active, null], [$subscription->plan->key, $subscription->status, $subscription->current_period_end]);
        $this->assertSame('free', $this->fresh($tenant)->plan->key);

        $limits = app(PlanLimits::class);
        $this->asTenant($tenant, function () use ($tenant, $limits) {
            $property = Property::factory()->for($tenant)->create();
            $this->assertSame(0, $limits->remainingProperties($tenant));
            $this->assertThrowsValidation(fn () => $limits->ensureCanAddProperties($tenant), 'plan_limit');

            Room::factory()->for($tenant)->count(10)->sequence(fn ($s) => ['property_id' => $property->id, 'number' => 'R'.$s->index])->create();
            $this->assertSame(0, $limits->remainingRooms($tenant));
            $this->assertThrowsValidation(fn () => $limits->ensureCanAddRooms($tenant), 'plan_limit');
        });
    }

    public function test_creating_the_free_subscription_twice_changes_nothing(): void
    {
        [$tenant] = $this->tenantOn('free');

        app(CreateFreeSubscription::class)($tenant);

        $this->assertSame(1, $this->asTenant($tenant, fn () => Subscription::count()));
    }

    // ---- Percobaan ----

    public function test_a_trial_opens_the_paid_plan_for_fourteen_days(): void
    {
        [$tenant] = $this->tenantOn('free');

        $subscription = app(StartTrial::class)($tenant, $this->plan('pro'));

        $fresh = $this->fresh($tenant);
        $this->assertSame(['pro', TenantStatus::Trial], [$fresh->plan->key, $fresh->status]);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->equalTo(now()->addDays(14)));
        $this->assertTrue($fresh->trial_ends_at->equalTo($subscription->trial_ends_at));
        $this->assertSame(3, $this->asTenant($tenant, fn () => app(PlanLimits::class)->remainingProperties($fresh)));
        $this->assertSame(SubscriptionStatus::Expired, $this->asTenant($tenant, fn () => Subscription::query()->where('plan_id', $this->plan('free')->id)->first())->status);
    }

    public function test_a_trial_is_only_given_once_and_only_for_paid_plans_from_the_free_plan(): void
    {
        [$tenant] = $this->tenantOn('free');
        app(StartTrial::class)($tenant, $this->plan('pro'));
        $this->at('2026-11-01 03:00:00');
        $this->lifecycle($tenant);
        $this->assertSame('free', $this->fresh($tenant)->plan->key);

        $this->assertThrowsValidation(fn () => app(StartTrial::class)($this->fresh($tenant), $this->plan('business')), 'plan');

        [$other] = $this->tenantOn('free');
        $this->assertThrowsValidation(fn () => app(StartTrial::class)($other, $this->plan('free')), 'plan');

        [$paying] = $this->tenantOn('starter');
        $this->assertThrowsValidation(fn () => app(StartTrial::class)($paying, $this->plan('pro')), 'plan');
    }

    public function test_a_trial_without_payment_returns_to_free_after_fourteen_days_not_suspended(): void
    {
        [$tenant] = $this->tenantOn('free');
        app(StartTrial::class)($tenant, $this->plan('pro'));

        $this->at('2026-10-28 03:00:00'); // hari ke-13
        $this->lifecycle($tenant);
        $this->assertSame(['pro', TenantStatus::Trial], [$this->fresh($tenant)->plan->key, $this->fresh($tenant)->status]);

        $this->at('2026-10-29 04:00:00'); // lewat 14 hari
        $this->lifecycle($tenant);

        $fresh = $this->fresh($tenant);
        $this->assertSame(['free', TenantStatus::Active, null], [$fresh->plan->key, $fresh->status, $fresh->trial_ends_at]);
        $this->assertSame([SubscriptionStatus::Active, 'free'], [$this->currentSubscription($tenant)->status, $this->currentSubscription($tenant)->plan->key]);
        $this->assertNull($fresh->suspended_at);
    }

    public function test_data_over_the_free_limit_is_kept_but_nothing_new_can_be_added_after_a_trial(): void
    {
        [$tenant] = $this->tenantOn('free');
        app(StartTrial::class)($tenant, $this->plan('pro'));
        $this->addRooms($tenant, 25);
        $this->at('2026-10-30 03:00:00');

        $this->lifecycle($tenant);

        $this->assertSame(25, $this->asTenant($tenant, fn () => Room::count()), 'Data tidak dihapus.');
        $fresh = $this->fresh($tenant);
        $this->assertSame(0, $this->asTenant($tenant, fn () => app(PlanLimits::class)->remainingRooms($fresh)));
        $this->assertThrowsValidation(fn () => $this->asTenant($tenant, fn () => app(PlanLimits::class)->ensureCanAddRooms($fresh)), 'plan_limit');
    }

    // ---- Berlangganan dan pembayaran ----

    public function test_subscribing_only_creates_a_request_and_an_invoice_and_the_plan_stays_until_paid(): void
    {
        [$tenant] = $this->tenantOn('free');

        $invoice = $this->subscribe($tenant, 'pro');

        $fresh = $this->fresh($tenant);
        $this->assertSame(['free', TenantStatus::Active], [$fresh->plan->key, $fresh->status], 'Paket berbayar tidak terbuka sebelum dibayar.');
        $this->assertSame(SubscriptionStatus::Active, $this->currentSubscription($tenant)->status);
        $this->assertSame('free', $this->currentSubscription($tenant)->plan->key);
        $this->assertSame([199_000, SubscriptionInvoiceStatus::Pending, '2026-10-15', '2026-11-14', '2026-10-22'], [
            $invoice->amount, $invoice->status, $invoice->period_start->toDateString(), $invoice->period_end->toDateString(), $invoice->due_date->toDateString(),
        ]);

        $request = $this->asTenant($tenant, fn () => Subscription::query()->pending()->sole());
        $this->assertSame(['pro', SubscriptionStatus::Pending], [$request->plan->key, $request->status]);
        $this->assertSame(10, $this->asTenant($tenant, fn () => app(PlanLimits::class)->remainingRooms($fresh)));
    }

    public function test_the_subscription_invoice_number_is_unique_across_the_platform(): void
    {
        [$a] = $this->tenantOn('free');
        [$b] = $this->tenantOn('free');

        $one = $this->subscribe($a, 'pro');
        $two = $this->subscribe($b, 'pro');

        $this->assertMatchesRegularExpression('/^SUB-202610-\d{6}$/', $one->number);
        $this->assertMatchesRegularExpression('/^SUB-202610-\d{6}$/', $two->number);
        $this->assertNotSame($one->number, $two->number);
        $this->assertSame(2, SubscriptionInvoice::withoutGlobalScopes()->distinct()->count('number'));
    }

    public function test_recording_the_transfer_makes_the_subscription_active_for_the_invoice_period(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');
        $admin = $this->admin();

        $paid = $this->pay($invoice, $admin);

        $this->assertSame([SubscriptionInvoiceStatus::Paid, 'Transfer bank', 'TRX-1'], [$paid->status, $paid->payment_method, $paid->reference]);
        $this->assertNotNull($paid->paid_at);
        $subscription = $this->currentSubscription($tenant);
        $this->assertSame([SubscriptionStatus::Active, null, '2026-10-15', '2026-11-14'], [
            $subscription->status, $subscription->grace_ends_at, $subscription->current_period_start->toDateString(), $subscription->current_period_end->toDateString(),
        ]);
        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);
    }

    public function test_a_yearly_subscription_bills_the_yearly_price_for_twelve_months(): void
    {
        [$tenant] = $this->tenantOn('free');

        $invoice = $this->subscribe($tenant, 'pro', BillingPeriod::Yearly);

        $this->assertSame([1_990_000, '2026-10-15', '2027-10-14'], [$invoice->amount, $invoice->period_start->toDateString(), $invoice->period_end->toDateString()]);
    }

    public function test_yearly_billing_is_refused_for_a_plan_without_a_yearly_price(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->plan('pro')->update(['price_yearly' => null]);

        $this->assertThrowsValidation(fn () => $this->subscribe($tenant, 'pro', BillingPeriod::Yearly), 'plan');
        $this->assertSame('free', $this->fresh($tenant)->plan->key);
    }

    public function test_only_paid_active_plans_can_be_subscribed_and_one_pending_invoice_at_a_time(): void
    {
        [$tenant] = $this->tenantOn('free');

        $this->assertThrowsValidation(fn () => $this->subscribe($tenant, 'free'), 'plan');
        $this->plan('business')->update(['is_active' => false]);
        $this->assertThrowsValidation(fn () => $this->subscribe($tenant, 'business'), 'plan');

        $this->subscribe($tenant, 'pro');
        $this->assertThrowsValidation(fn () => $this->subscribe($tenant, 'starter'), 'plan');
        $this->assertCount(1, $this->invoicesOf($tenant));
    }

    public function test_an_unpaid_request_lapses_after_its_due_date_and_voids_its_invoice(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->subscribe($tenant, 'pro');

        $this->at('2026-10-22 03:00:00'); // hari jatuh tempo: masih berlaku
        $this->lifecycle($tenant);
        $this->assertSame(1, $this->asTenant($tenant, fn () => Subscription::query()->pending()->count()));

        $this->at('2026-10-23 03:00:00'); // lewat jatuh tempo
        $this->lifecycle($tenant);

        $this->assertSame(0, $this->asTenant($tenant, fn () => Subscription::query()->pending()->count()));
        $this->assertSame([SubscriptionInvoiceStatus::Void], $this->invoicesOf($tenant)->pluck('status')->all());
        $this->assertSame(['free', TenantStatus::Active], [$this->fresh($tenant)->plan->key, $this->fresh($tenant)->status]);
    }

    public function test_the_subscribe_and_let_it_lapse_loop_never_gives_a_paid_plan_without_paying(): void
    {
        [$tenant] = $this->tenantOn('free');

        foreach (range(0, 3) as $round) {
            $this->at(CarbonImmutable::parse('2026-10-15 03:00:00', 'UTC')->addDays($round * 9)->toDateTimeString());
            $this->subscribe($tenant, 'business', BillingPeriod::Yearly);
            $this->assertSame('free', $this->fresh($tenant)->plan->key, "Putaran {$round}: paket berubah sebelum dibayar.");
            $this->assertSame(10, $this->asTenant($tenant, fn () => app(PlanLimits::class)->remainingRooms($this->fresh($tenant))));
            $this->assertThrowsValidation(fn () => $this->asTenant($tenant, fn () => app(PlanFeatures::class)->ensure($this->fresh($tenant), 'reports.export')), 'plan_limit');

            $this->at(CarbonImmutable::parse('2026-10-15 03:00:00', 'UTC')->addDays($round * 9 + 8)->toDateTimeString());
            $this->lifecycle($tenant);
            $this->assertSame('free', $this->fresh($tenant)->plan->key);
        }

        $this->assertSame(SubscriptionStatus::Active, $this->currentSubscription($tenant)->status);
    }

    public function test_paying_a_request_opens_the_plan_from_the_payment_date_not_the_request_date(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');

        $this->at('2026-10-20 03:00:00');
        $paid = $this->pay($invoice);

        $subscription = $this->currentSubscription($tenant);
        $this->assertSame(['pro', SubscriptionStatus::Active, '2026-10-20', '2026-11-19'], [
            $this->fresh($tenant)->plan->key, $subscription->status, $subscription->current_period_start->toDateString(), $subscription->current_period_end->toDateString(),
        ]);
        $this->assertSame(['2026-10-20', '2026-11-19'], [$paid->period_start->toDateString(), $paid->period_end->toDateString()], 'Tagihan menyesuaikan periode yang sebenarnya.');
        $this->assertSame(SubscriptionStatus::Canceled, $this->asTenant($tenant, fn () => Subscription::query()->where('plan_id', $this->plan('free')->id)->first())->status);
        $this->assertSame(3, $this->asTenant($tenant, fn () => app(PlanLimits::class)->remainingProperties($this->fresh($tenant))));
    }

    public function test_a_request_made_during_a_trial_replaces_the_trial_only_when_paid(): void
    {
        [$tenant] = $this->tenantOn('free');
        app(StartTrial::class)($tenant, $this->plan('pro'));
        $invoice = $this->subscribe($tenant, 'business');

        $this->assertSame(['pro', SubscriptionStatus::Trialing], [$this->fresh($tenant)->plan->key, $this->currentSubscription($tenant)->status]);

        $this->pay($invoice);

        $this->assertSame(['business', SubscriptionStatus::Active], [$this->fresh($tenant)->plan->key, $this->currentSubscription($tenant)->status]);
        $this->assertNull($this->fresh($tenant)->trial_ends_at);
    }

    public function test_a_paid_invoice_cannot_be_paid_again_or_voided_and_a_voided_one_cannot_be_paid(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');
        $this->pay($invoice);

        $this->assertThrowsValidation(fn () => $this->pay($invoice), 'general');
        $this->assertThrowsValidation(fn () => app(VoidSubscriptionInvoice::class)($invoice), 'general');

        [$other] = $this->tenantOn('free');
        $second = $this->subscribe($other, 'starter');
        app(VoidSubscriptionInvoice::class)($second);
        $this->assertThrowsValidation(fn () => $this->pay($second), 'general');
    }

    public function test_payment_input_is_validated(): void
    {
        [$tenant] = $this->tenantOn('free');
        $invoice = $this->subscribe($tenant, 'pro');

        $this->assertThrowsValidation(fn () => app(RecordSubscriptionPayment::class)($invoice, '  ', null, null, $this->admin()), 'payment_method');
        $this->assertSame(SubscriptionInvoiceStatus::Pending, $this->invoicesOf($tenant)->first()->status);
    }

    // ---- Perpanjangan, masa tenggang, penangguhan ----

    public function test_renewal_invoice_is_created_a_week_before_the_end_once(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro')); // periode 15 Okt - 14 Nov

        $this->at('2026-11-06 03:00:00'); // 8 hari sebelum berakhir
        $this->lifecycle($tenant);
        $this->assertCount(1, $this->invoicesOf($tenant));

        $this->at('2026-11-07 03:00:00'); // 7 hari sebelum berakhir
        $this->lifecycle($tenant);
        $this->lifecycle($tenant);

        $renewal = $this->invoicesOf($tenant)->last();
        $this->assertCount(2, $this->invoicesOf($tenant));
        $this->assertSame([SubscriptionInvoiceStatus::Pending, '2026-11-15', '2026-12-14', 199_000], [
            $renewal->status, $renewal->period_start->toDateString(), $renewal->period_end->toDateString(), $renewal->amount,
        ]);
    }

    public function test_an_unpaid_renewal_enters_grace_then_suspends_and_a_payment_reopens_the_tenant(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro'));
        $this->at('2026-11-07 03:00:00');
        $this->lifecycle($tenant);

        $this->at('2026-11-14 03:00:00'); // hari terakhir periode: belum menunggak
        $this->lifecycle($tenant);
        $this->assertSame(SubscriptionStatus::Active, $this->currentSubscription($tenant)->status);

        $this->at('2026-11-15 03:00:00'); // periode lewat
        $this->lifecycle($tenant);
        $subscription = $this->currentSubscription($tenant);
        $this->assertSame([SubscriptionStatus::PastDue, 'pro', TenantStatus::Active], [$subscription->status, $this->fresh($tenant)->plan->key, $this->fresh($tenant)->status]);
        $this->assertTrue($subscription->grace_ends_at->equalTo(CarbonImmutable::parse('2026-11-21 16:00:00', 'UTC')));

        $this->at('2026-11-21 15:00:00'); // masih dalam tenggang 7 hari
        $this->lifecycle($tenant);
        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);

        $this->at('2026-11-21 17:00:00'); // tenggang habis
        $this->lifecycle($tenant);
        $suspended = $this->fresh($tenant);
        $this->assertSame([TenantStatus::Suspended, 'pro'], [$suspended->status, $suspended->plan->key]);
        $this->assertNotNull($suspended->suspended_at);

        $renewal = $this->invoicesOf($tenant)->last();
        $this->pay($renewal);

        $reopened = $this->fresh($tenant);
        $this->assertSame([TenantStatus::Active, null], [$reopened->status, $reopened->suspended_at]);
        $subscription = $this->currentSubscription($tenant);
        $this->assertSame([SubscriptionStatus::Active, '2026-11-15', '2026-12-14'], [$subscription->status, $subscription->current_period_start->toDateString(), $subscription->current_period_end->toDateString()]);
    }

    public function test_paying_the_renewal_early_moves_the_period_forward_without_any_grace(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro'));
        $this->at('2026-11-08 03:00:00');
        $this->lifecycle($tenant);
        $this->pay($this->invoicesOf($tenant)->last());

        $this->at('2026-11-20 03:00:00');
        $this->lifecycle($tenant);

        $subscription = $this->currentSubscription($tenant);
        $this->assertSame([SubscriptionStatus::Active, '2026-12-14'], [$subscription->status, $subscription->current_period_end->toDateString()]);
        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);
    }

    public function test_the_daily_check_is_idempotent(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro'));
        $this->at('2026-11-16 03:00:00');

        $this->lifecycle($tenant);
        $snapshot = [$this->currentSubscription($tenant)->only(['id', 'status', 'grace_ends_at']), $this->invoicesOf($tenant)->count()];
        $this->lifecycle($tenant);
        $this->lifecycle($tenant);

        $this->assertEquals($snapshot, [$this->currentSubscription($tenant)->only(['id', 'status', 'grace_ends_at']), $this->invoicesOf($tenant)->count()]);
    }

    public function test_free_tenants_are_untouched_by_the_daily_check(): void
    {
        [$tenant] = $this->tenantOn('free');
        $before = $this->currentSubscription($tenant)->only(['id', 'status']);
        $this->at('2027-06-01 03:00:00');

        $this->lifecycle($tenant);

        $this->assertEquals($before, $this->currentSubscription($tenant)->only(['id', 'status']));
        $this->assertCount(0, $this->invoicesOf($tenant));
    }

    public function test_a_tenant_without_any_subscription_row_gets_one_instead_of_failing(): void
    {
        [$tenant] = $this->tenantWithOwner('starter');

        $this->lifecycle($tenant);

        $this->assertSame(['starter', SubscriptionStatus::Active], [$this->currentSubscription($tenant)->plan->key, $this->currentSubscription($tenant)->status]);
    }

    // ---- Turun paket ----

    public function test_downgrade_is_refused_when_rooms_exceed_the_target_plans_limit(): void
    {
        [$tenant] = $this->tenantOn('pro');
        $this->addRooms($tenant, 40);

        try {
            app(DowngradePlan::class)($tenant, $this->plan('starter'));
            $this->fail('Turun paket seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('40 kamar', $e->errors()['plan'][0]);
            $this->assertStringContainsString('batas paket Starter adalah 30', $e->errors()['plan'][0]);
        }

        $this->assertSame('pro', $this->fresh($tenant)->plan->key);
        $this->assertSame(40, $this->asTenant($tenant, fn () => Room::count()), 'Data tidak dihapus.');
    }

    public function test_downgrade_checks_properties_and_users_too(): void
    {
        [$tenant] = $this->tenantOn('pro');
        $this->propertyFor($tenant);
        $this->propertyFor($tenant);
        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($tenant, $this->plan('starter')), 'plan');

        [$crowded] = $this->tenantOn('pro');
        User::factory()->for($crowded)->staff()->create();
        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($crowded, $this->plan('free')), 'plan');
    }

    public function test_a_valid_downgrade_keeps_the_period_and_voids_pending_invoices(): void
    {
        [$tenant] = $this->tenantOn('pro');
        $period = $this->currentSubscription($tenant)->only(['current_period_start', 'current_period_end']);
        $this->addRooms($tenant, 20);

        $subscription = app(DowngradePlan::class)($tenant, $this->plan('starter'));

        $this->assertSame(['starter', SubscriptionStatus::Active], [$this->fresh($tenant)->plan->key, $subscription->status]);
        $this->assertEquals($period['current_period_end'], $subscription->current_period_end);
        $this->assertSame(20, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_downgrading_to_free_ends_the_paid_subscription(): void
    {
        [$tenant] = $this->tenantOn('pro');
        $this->addRooms($tenant, 5);

        $subscription = app(DowngradePlan::class)($tenant, $this->plan('free'));

        $this->assertSame(['free', null], [$this->fresh($tenant)->plan->key, $subscription->current_period_end]);
        $this->assertSame(1, $this->asTenant($tenant, fn () => Subscription::query()->current()->count()));
    }

    public function test_downgrade_must_go_to_a_cheaper_plan(): void
    {
        [$tenant] = $this->tenantOn('starter');

        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($tenant, $this->plan('pro')), 'plan');
        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($tenant, $this->plan('starter')), 'plan');
    }

    public function test_downgrading_to_a_cheaper_paid_plan_needs_an_active_subscription(): void
    {
        // Percobaan: hanya boleh turun ke Gratis.
        [$trial] = $this->tenantOn('free');
        app(StartTrial::class)($trial, $this->plan('business'));
        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($this->fresh($trial), $this->plan('starter')), 'plan');
        $this->assertSame('business', $this->fresh($trial)->plan->key);
        app(DowngradePlan::class)($this->fresh($trial), $this->plan('free'));
        $this->assertSame('free', $this->fresh($trial)->plan->key);

        // Perpanjangan menunggak (lewat jatuh tempo): hanya boleh turun ke Gratis.
        [$late] = $this->tenantOn('free');
        $this->pay($this->subscribe($late, 'pro'));
        $this->at('2026-11-16 03:00:00');
        $this->lifecycle($late);
        $this->assertSame(SubscriptionStatus::PastDue, $this->currentSubscription($late)->status);
        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($late, $this->plan('starter')), 'plan');
        $this->assertSame('pro', $this->fresh($late)->plan->key);
    }

    public function test_a_pending_request_cannot_be_used_to_reach_a_paid_plan_through_a_downgrade(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->subscribe($tenant, 'business', BillingPeriod::Yearly);

        $this->assertThrowsValidation(fn () => app(DowngradePlan::class)($tenant, $this->plan('starter')), 'plan');

        $this->assertSame('free', $this->fresh($tenant)->plan->key);
        $this->assertSame(0, $this->asTenant($tenant, fn () => Subscription::query()->current()->whereHas('plan', fn ($q) => $q->where('price_monthly', '>', 0))->count()));
    }

    public function test_a_payment_does_not_reopen_a_tenant_the_admin_suspended_by_hand(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro'));
        $this->at('2026-11-08 03:00:00');
        $this->lifecycle($tenant);
        app(SuspendTenant::class)($tenant); // manual
        $this->assertSame('manual', $this->fresh($tenant)->settings['suspension_reason']);

        $this->pay($this->invoicesOf($tenant)->last());

        $this->assertSame(TenantStatus::Suspended, $this->fresh($tenant)->status, 'Pembayaran tidak membatalkan keputusan Super Admin.');
        $this->assertSame(SubscriptionStatus::Active, $this->currentSubscription($tenant)->status);

        app(ActivateTenant::class)($tenant);
        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);
        $this->assertArrayNotHasKey('suspension_reason', $this->fresh($tenant)->settings ?? []);
    }

    public function test_a_tenant_suspended_without_a_recorded_reason_is_treated_as_manual(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro'));
        $this->at('2026-11-08 03:00:00');
        $this->lifecycle($tenant);
        $tenant->update(['status' => TenantStatus::Suspended, 'suspended_at' => now(), 'settings' => null]);

        $this->pay($this->invoicesOf($tenant)->last());

        $this->assertSame(TenantStatus::Suspended, $this->fresh($tenant)->status);
    }

    public function test_the_arrears_suspension_is_tagged_so_that_paying_reopens_it(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->pay($this->subscribe($tenant, 'pro'));
        $this->at('2026-11-07 03:00:00');
        $this->lifecycle($tenant);
        $this->at('2026-11-15 03:00:00'); // periode lewat: masuk masa tenggang
        $this->lifecycle($tenant);
        $this->at('2026-11-21 17:00:00'); // tenggang habis
        $this->lifecycle($tenant);

        $this->assertSame(['suspended', 'arrears'], [$this->fresh($tenant)->status->value, $this->fresh($tenant)->settings['suspension_reason']]);

        $this->pay($this->invoicesOf($tenant)->last());

        $this->assertSame(TenantStatus::Active, $this->fresh($tenant)->status);
        $this->assertArrayNotHasKey('suspension_reason', $this->fresh($tenant)->settings ?? []);
    }

    // ---- Upgrade dan penetapan oleh Super Admin ----

    public function test_upgrading_opens_the_new_limits_the_moment_the_payment_is_recorded(): void
    {
        [$tenant] = $this->tenantOn('free');
        $limits = app(PlanLimits::class);
        $this->addRooms($tenant, 10);
        $this->assertSame(0, $this->asTenant($tenant, fn () => $limits->remainingRooms($this->fresh($tenant))));

        $invoice = $this->subscribe($tenant, 'pro');
        $this->assertSame(0, $this->asTenant($tenant, fn () => $limits->remainingRooms($this->fresh($tenant))), 'Belum dibayar: batas lama.');

        $this->pay($invoice);

        $this->assertSame(90, $this->asTenant($tenant, fn () => $limits->remainingRooms($this->fresh($tenant))));
        $this->assertSame('reports.export', $this->fresh($tenant)->plan->features[0]);
    }

    public function test_the_super_admin_can_set_a_plan_directly_and_it_cancels_pending_requests_and_invoices(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->subscribe($tenant, 'starter');

        app(SetTenantPlan::class)($tenant, $this->plan('business'), BillingPeriod::Yearly);

        $subscription = $this->currentSubscription($tenant);
        $this->assertSame(['business', SubscriptionStatus::Active, '2027-10-14'], [$this->fresh($tenant)->plan->key, $subscription->status, $subscription->current_period_end->toDateString()]);
        $this->assertSame([SubscriptionInvoiceStatus::Void], $this->invoicesOf($tenant)->pluck('status')->all());
        $this->assertSame(0, $this->asTenant($tenant, fn () => Subscription::query()->pending()->count()));
    }

    public function test_the_super_admin_cannot_set_a_yearly_period_on_a_plan_without_a_yearly_price_or_touch_a_closed_tenant(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->plan('pro')->update(['price_yearly' => null]);

        $this->assertThrowsValidation(fn () => app(SetTenantPlan::class)($tenant, $this->plan('pro'), BillingPeriod::Yearly), 'plan');

        $tenant->update(['status' => TenantStatus::Closed]);
        $this->assertThrowsValidation(fn () => app(SetTenantPlan::class)($tenant, $this->plan('pro'), BillingPeriod::Monthly), 'plan');
    }

    public function test_setting_a_smaller_plan_respects_the_limits_unless_explicitly_ignored(): void
    {
        [$tenant] = $this->tenantOn('pro');
        $this->addRooms($tenant, 50);

        $this->assertThrowsValidation(fn () => app(SetTenantPlan::class)($tenant, $this->plan('starter'), BillingPeriod::Monthly), 'plan');
        $this->assertSame('pro', $this->fresh($tenant)->plan->key);

        app(SetTenantPlan::class)($tenant, $this->plan('starter'), BillingPeriod::Monthly, ignoreLimits: true);
        $this->assertSame('starter', $this->fresh($tenant)->plan->key);
    }

    public function test_an_inactive_plan_cannot_be_assigned(): void
    {
        [$tenant] = $this->tenantOn('free');
        $this->plan('pro')->update(['is_active' => false]);

        $this->assertThrowsValidation(fn () => app(SetTenantPlan::class)($tenant, $this->plan('pro'), BillingPeriod::Monthly), 'plan');
    }

    // ---- Penangguhan ----

    public function test_suspending_and_activating_a_tenant(): void
    {
        [$tenant] = $this->tenantOn('pro');

        app(SuspendTenant::class)($tenant);
        $this->assertSame(TenantStatus::Suspended, $this->fresh($tenant)->status);
        $this->assertNotNull($this->fresh($tenant)->suspended_at);

        app(SuspendTenant::class)($tenant); // idempoten
        app(ActivateTenant::class)($tenant);
        $this->assertSame([TenantStatus::Active, null], [$this->fresh($tenant)->status, $this->fresh($tenant)->suspended_at]);
    }

    public function test_activating_a_trialing_tenant_returns_it_to_trial_and_closed_tenants_are_untouchable(): void
    {
        [$tenant] = $this->tenantOn('free');
        app(StartTrial::class)($tenant, $this->plan('pro'));
        app(SuspendTenant::class)($tenant);

        app(ActivateTenant::class)($tenant);
        $this->assertSame(TenantStatus::Trial, $this->fresh($tenant)->status);

        $tenant->update(['status' => TenantStatus::Closed]);
        $this->assertThrowsValidation(fn () => app(SuspendTenant::class)($tenant), 'general');
        $this->assertThrowsValidation(fn () => app(ActivateTenant::class)($tenant), 'general');
    }

    public function test_changing_plans_never_reopens_a_suspended_tenant(): void
    {
        [$tenant] = $this->tenantOn('pro');
        app(SuspendTenant::class)($tenant);

        app(SetTenantPlan::class)($tenant, $this->plan('business'), BillingPeriod::Monthly);

        $this->assertSame([TenantStatus::Suspended, 'business'], [$this->fresh($tenant)->status, $this->fresh($tenant)->plan->key]);
    }

    // ---- Penjadwal ----

    public function test_the_daily_command_queues_one_job_per_tenant_that_is_not_closed(): void
    {
        Queue::fake();
        [$active] = $this->tenantOn('free');
        [$suspended] = $this->tenantOn('pro');
        [$closed] = $this->tenantOn('free');
        app(SuspendTenant::class)($suspended);
        $closed->update(['status' => TenantStatus::Closed]);

        $this->artisan('subscriptions:run')->assertSuccessful();

        Queue::assertPushed(RunTenantSubscription::class, 2);
        Queue::assertPushed(RunTenantSubscription::class, fn ($job) => in_array($job->tenantId, [$active->id, $suspended->id], true));
        Queue::assertNotPushed(RunTenantSubscription::class, fn ($job) => $job->tenantId === $closed->id);
    }

    public function test_the_job_runs_the_lifecycle_for_its_tenant_without_a_logged_in_user(): void
    {
        [$tenant] = $this->tenantOn('free');
        app(StartTrial::class)($tenant, $this->plan('pro'));
        $this->at('2026-11-01 03:00:00');
        app(TenantContext::class)->forget();

        (new RunTenantSubscription($tenant->id))->handle(app(RunSubscriptionLifecycle::class));

        $this->assertSame('free', $this->fresh($tenant)->plan->key);
    }

    public function test_the_lifecycle_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'subscriptions:run'));

        $this->assertCount(1, $events);
        $this->assertSame('30 1 * * *', $events->first()->expression);
    }

    private function assertThrowsValidation(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail("ValidationException ({$key}) diharapkan.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
