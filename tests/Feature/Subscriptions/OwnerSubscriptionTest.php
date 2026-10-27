<?php

namespace Tests\Feature\Subscriptions;

use App\Actions\Subscriptions\SuspendTenant;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SubscriptionFixtures;
use Tests\TestCase;

class OwnerSubscriptionTest extends TestCase
{
    use RefreshDatabase, SubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSubscriptions();
        config(['kostra.subscription' => ['bank' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'PT Kostra Digital']]);
    }

    private function fresh(Tenant $tenant): Tenant
    {
        return Tenant::query()->findOrFail($tenant->getKey());
    }

    // ---- Halaman ----

    public function test_the_owner_sees_the_plan_usage_and_the_available_plans(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->addRooms($tenant, 3);

        $this->actingAs($owner)->get(route('owner.subscription.show'))->assertOk()
            ->assertSee('data-stat="plan">Gratis</div>', false)
            ->assertSee('3 / 10')->assertSee('1 / 1')
            ->assertSee('Starter')->assertSee('Pro')->assertSee('Business')
            ->assertSee('Rp 79.000')->assertSee('Coba 14 hari')->assertSee('Berlangganan bulanan');
    }

    public function test_staff_and_guests_cannot_open_the_subscription_page_or_its_actions(): void
    {
        [$tenant] = $this->tenantOn('free');
        $staff = User::factory()->for($tenant)->staff()->create();
        $payload = ['plan_id' => $this->plan('pro')->id];

        $this->actingAs($staff)->get(route('owner.subscription.show'))->assertForbidden();
        $this->actingAs($staff)->post(route('owner.subscription.trial'), $payload)->assertForbidden();
        $this->actingAs($staff)->post(route('owner.subscription.subscribe'), $payload)->assertForbidden();
        $this->actingAs($staff)->post(route('owner.subscription.downgrade'), $payload)->assertForbidden();
        $this->actingAs($staff)->get(route('owner.dashboard'))->assertDontSee(route('owner.subscription.show'), false);

        auth()->logout();
        $this->get(route('owner.subscription.show'))->assertRedirect(route('login'));
        $this->assertSame('free', $this->fresh($tenant)->plan->key);
    }

    public function test_the_menu_shows_the_subscription_entry_to_the_owner(): void
    {
        [, $owner] = $this->tenantOn('free');

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertSee(route('owner.subscription.show'), false)->assertDontSee('Segera');
    }

    // ---- Percobaan ----

    public function test_the_owner_starts_a_trial_and_cannot_start_a_second_one(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');

        $this->actingAs($owner)->post(route('owner.subscription.trial'), ['plan_id' => $this->plan('pro')->id])
            ->assertRedirect(route('owner.subscription.show'))->assertSessionHas('status');

        $this->actingAs($owner)->get(route('owner.subscription.show'))->assertOk()
            ->assertSee('data-stat="plan">Pro</div>', false)->assertSee('Percobaan');
        $this->assertSame('pro', $this->fresh($tenant)->plan->key);

        $this->travelTo(now()->addDays(15));
        $this->lifecycle($tenant);
        $this->actingAs($owner)->get(route('owner.subscription.show'))->assertDontSee('Coba 14 hari');
        $this->actingAs($owner)->get(route('owner.subscription.show'));
        $this->actingAs($owner)->post(route('owner.subscription.trial'), ['plan_id' => $this->plan('business')->id])->assertSessionHasErrors('plan');
    }

    // ---- Berlangganan ----

    public function test_subscribing_shows_the_invoice_and_how_to_pay_and_blocks_a_second_request(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');

        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => $this->plan('pro')->id, 'billing_period' => 'monthly'])
            ->assertRedirect(route('owner.subscription.show'))->assertSessionHas('status');

        $invoice = $this->invoicesOf($tenant)->sole();
        $this->actingAs($owner)->get(route('owner.subscription.show'))->assertOk()
            ->assertSee($invoice->number)->assertSee('Rp 199.000')->assertSee('Menunggu pembayaran')
            ->assertSee('BCA')->assertSee('1234567890')->assertSee('PT Kostra Digital')
            ->assertSee('Masih ada tagihan langganan yang menunggu pembayaran')
            ->assertDontSee('Berlangganan bulanan');

        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => $this->plan('business')->id])->assertSessionHasErrors('plan');
        $this->assertCount(1, $this->invoicesOf($tenant));
    }

    public function test_the_owner_can_choose_yearly_billing(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');

        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => $this->plan('starter')->id, 'billing_period' => 'yearly'])
            ->assertSessionHasNoErrors();

        $this->assertSame(790_000, $this->invoicesOf($tenant)->sole()->amount);
    }

    public function test_plan_ids_are_validated_and_inactive_plans_cannot_be_chosen(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->plan('business')->update(['is_active' => false]);

        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => 999999])->assertSessionHasErrors('plan_id');
        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => $this->plan('business')->id])->assertSessionHasErrors('plan_id');
        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => $this->plan('pro')->id, 'billing_period' => 'weekly'])->assertSessionHasErrors('billing_period');
        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), [])->assertSessionHasErrors('plan_id');

        $this->assertCount(0, $this->invoicesOf($tenant));
    }

    // ---- Turun paket ----

    public function test_a_downgrade_that_does_not_fit_explains_why_and_changes_nothing(): void
    {
        [$tenant, $owner] = $this->tenantOn('pro');
        $this->addRooms($tenant, 40);

        $this->actingAs($owner)->get(route('owner.subscription.show'));
        $this->actingAs($owner)->post(route('owner.subscription.downgrade'), ['plan_id' => $this->plan('starter')->id])->assertSessionHasErrors('plan');

        $this->actingAs($owner)->followingRedirects()->get(route('owner.subscription.show'))->assertOk();
        $this->assertSame('pro', $this->fresh($tenant)->plan->key);
    }

    public function test_a_valid_downgrade_works(): void
    {
        [$tenant, $owner] = $this->tenantOn('pro');
        $this->addRooms($tenant, 5);

        $this->actingAs($owner)->post(route('owner.subscription.downgrade'), ['plan_id' => $this->plan('starter')->id])->assertSessionHasNoErrors();

        $this->assertSame('starter', $this->fresh($tenant)->plan->key);
    }

    // ---- Tenant ditangguhkan ----

    public function test_a_suspended_owner_can_see_the_page_and_export_but_not_change_anything(): void
    {
        [$tenant, $owner] = $this->tenantOn('pro');
        app(SuspendTenant::class)($tenant);

        $this->actingAs($owner)->get(route('owner.subscription.show'))->assertOk()->assertSee('Akun ditangguhkan');
        $this->actingAs($owner)->get(route('owner.export.index'))->assertOk();
        $this->actingAs($owner)->get(route('owner.export.download'))->assertOk();
        $this->actingAs($owner)->post(route('owner.subscription.downgrade'), ['plan_id' => $this->plan('starter')->id])->assertForbidden();
        $this->actingAs($owner)->post(route('owner.subscription.subscribe'), ['plan_id' => $this->plan('business')->id])->assertForbidden();
    }

    // ---- Penegakan batas ----

    public function test_inviting_staff_stops_at_the_plan_user_limit_with_an_upgrade_offer(): void
    {
        [$tenant, $owner] = $this->tenantOn('free'); // 1 pengguna: hanya pemilik

        $this->actingAs($owner)->get(route('owner.staff.index'));
        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'staf@example.com'])
            ->assertSessionHasErrors('plan_limit');

        $this->actingAs($owner)->followingRedirects()->get(route('owner.staff.index'))->assertOk();
        $this->assertSame(0, $this->asTenant($tenant, fn () => Invitation::count()));
        $this->assertStringContainsString('Naik paket', session('errors')?->first('plan_limit') ?? 'Naik paket');
    }

    public function test_pending_invitations_count_toward_the_limit(): void
    {
        [$tenant, $owner] = $this->tenantOn('starter'); // 2 pengguna: pemilik + satu

        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'satu@example.com'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'dua@example.com'])->assertSessionHasErrors('plan_limit');

        $this->assertSame(1, $this->asTenant($tenant, fn () => Invitation::count()));
    }

    public function test_upgrading_opens_room_for_more_staff_at_once(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'staf@example.com'])->assertSessionHasErrors('plan_limit');

        $this->subscribe($tenant, 'pro');
        $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => 'staf@example.com'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->asTenant($tenant, fn () => Invitation::count()));
    }

    public function test_a_plan_without_a_user_limit_never_blocks_invitations(): void
    {
        [, $owner] = $this->tenantOn('business');

        foreach (range(1, 6) as $i) {
            $this->actingAs($owner)->post(route('owner.staff.invite'), ['email' => "staf{$i}@example.com"])->assertSessionHasNoErrors();
        }
    }

    public function test_report_export_is_a_plan_feature_with_an_upgrade_offer_and_screens_stay_free(): void
    {
        [, $free] = $this->tenantOn('free');
        [, $starter] = $this->tenantOn('starter');

        $this->actingAs($free)->get(route('owner.reports.show', 'pemasukan'))->assertOk()->assertSee('Unduh Excel');
        $this->actingAs($free)->get(route('owner.reports.show', 'pemasukan'));
        $blocked = $this->actingAs($free)->get(route('owner.reports.show', ['report' => 'pemasukan', 'format' => 'xlsx']));
        $blocked->assertSessionHasErrors('plan_limit');
        $this->assertStringContainsString('Naik paket', session('errors')->first('plan_limit'));
        $this->actingAs($free)->get(route('owner.reports.show', ['report' => 'tunggakan', 'format' => 'pdf']))->assertSessionHasErrors('plan_limit');

        $this->actingAs($starter)->get(route('owner.reports.show', ['report' => 'pemasukan', 'format' => 'xlsx']))->assertOk();
        $this->actingAs($starter)->get(route('owner.reports.show', ['report' => 'tunggakan', 'format' => 'pdf']))->assertOk();
    }
}
