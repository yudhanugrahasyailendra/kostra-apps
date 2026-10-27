<?php

namespace Tests\Concerns;

use App\Actions\Subscriptions\CreateFreeSubscription;
use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Actions\Subscriptions\RequestSubscription;
use App\Actions\Subscriptions\RunSubscriptionLifecycle;
use App\Actions\Subscriptions\SetTenantPlan;
use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fixture langganan. Tanggal dibekukan (15 Oktober 2026, 11:00 WITA) supaya masa percobaan, tenggang, dan
 * periode deterministik. Paket diisi seeder dari config/kostra.php.
 */
trait SubscriptionFixtures
{
    use CreatesTenantFixtures;

    protected function setUpSubscriptions(string $today = '2026-10-15'): void
    {
        $this->travelTo(CarbonImmutable::parse($today.' 03:00:00', 'UTC'));
        $this->seedPlans();
    }

    protected function plan(string $key): Plan
    {
        return Plan::query()->where('key', $key)->firstOrFail();
    }

    /**
     * Tenant dan pemilik di paket $planKey, dengan langganan aktif yang sesuai (bukan hanya kolom plan_id).
     *
     * @return array{0: Tenant, 1: User}
     */
    protected function tenantOn(string $planKey = 'free'): array
    {
        [$tenant, $owner] = $this->tenantWithOwner('free');

        if ($planKey !== 'free') {
            app(SetTenantPlan::class)($tenant, $this->plan($planKey), BillingPeriod::Monthly);
            $tenant->refresh();
        } else {
            app(CreateFreeSubscription::class)($tenant);
        }

        return [$tenant, $owner];
    }

    protected function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    protected function lifecycle(Tenant $tenant): void
    {
        app(RunSubscriptionLifecycle::class)(Tenant::query()->findOrFail($tenant->getKey()));
    }

    protected function currentSubscription(Tenant $tenant): ?Subscription
    {
        return $this->asTenant($tenant, fn () => Subscription::query()->current()->with('plan')->latest('id')->first());
    }

    /**
     * @return Collection<int, SubscriptionInvoice>
     */
    protected function invoicesOf(Tenant $tenant)
    {
        return $this->asTenant($tenant, fn () => SubscriptionInvoice::query()->orderBy('id')->get());
    }

    protected function subscribe(Tenant $tenant, string $planKey, BillingPeriod $period = BillingPeriod::Monthly): SubscriptionInvoice
    {
        return app(RequestSubscription::class)($tenant->fresh(), $this->plan($planKey), $period);
    }

    protected function pay(SubscriptionInvoice $invoice, ?User $by = null, ?CarbonImmutable $on = null): SubscriptionInvoice
    {
        return app(RecordSubscriptionPayment::class)($invoice, 'Transfer bank', 'TRX-1', $on, $by ?? $this->admin());
    }

    protected function addRooms(Tenant $tenant, int $count, ?int $propertyId = null): void
    {
        $property = $propertyId ?? $this->propertyFor($tenant)->id;

        $this->asTenant($tenant, function () use ($tenant, $count, $property) {
            for ($i = 0; $i < $count; $i++) {
                Room::factory()->for($tenant)->create(['property_id' => $property, 'number' => 'S'.$i.'-'.$property]);
            }
        });
    }
}
