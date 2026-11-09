<?php

namespace App\Http\Controllers\Owner;

use App\Actions\ChangeSubscriptionPlan;
use App\Actions\StartSubscriptionTrial;
use App\Enums\SubscriptionPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ChangePlanRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionInvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\Admin\TenantOverview;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(TenantOverview $overview): Response
    {
        $this->authorize('viewAny', Subscription::class);

        $tenant = TenantContext::instance()->tenant();
        $subscription = $tenant->currentSubscription();
        $subscription?->load('plan');

        return Inertia::render('Owner/Subscription/Index', [
            'plans' => PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('sort_order')->get())->resolve(),
            'currentPlanKey' => $tenant->plan->key,
            'subscription' => $subscription ? (new SubscriptionResource($subscription))->resolve() : null,
            'usage' => $overview->rows(collect([$tenant]))[$tenant->id]['usage'],
            'invoices' => SubscriptionInvoiceResource::collection(SubscriptionInvoice::query()->latest('id')->limit(24)->get())->resolve(),
            'trialAvailable' => StartSubscriptionTrial::eligible($tenant),
            'trialDays' => (int) config('kostra.subscription.trial_days'),
            'can' => ['manage' => auth()->user()->can('create', Subscription::class)],
        ]);
    }

    public function update(ChangePlanRequest $request, ChangeSubscriptionPlan $change): RedirectResponse
    {
        $tenant = TenantContext::instance()->tenant();
        $plan = Plan::query()->where('key', $request->validated('plan_key'))->firstOrFail();

        $subscription = $change->handle($tenant, $plan, SubscriptionPeriod::from($request->validated('billing_period')));

        $message = $subscription->isTrialing()
            ? "Masa percobaan paket {$plan->name} dimulai. Bayar tagihan sebelum percobaan berakhir agar paket berlanjut."
            : "Paket diubah ke {$plan->name}.";

        return back()->with('success', $message);
    }
}
