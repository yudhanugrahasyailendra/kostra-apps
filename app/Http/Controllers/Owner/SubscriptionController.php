<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Subscriptions\CreateFreeSubscription;
use App\Actions\Subscriptions\DowngradePlan;
use App\Actions\Subscriptions\RequestSubscription;
use App\Actions\Subscriptions\StartTrial;
use App\Enums\SubscriptionInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SubscriptionPlanRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\Messaging\MessageQuota;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(TenantContext $context, PlanLimits $limits, MessageQuota $quota, CreateFreeSubscription $createFree): View
    {
        Gate::authorize('subscription.manage');

        $tenant = $context->current();
        $subscription = Subscription::query()->current()->with('plan')->latest('id')->first() ?? $createFree($tenant)->load('plan');

        return view('owner.subscription', [
            'tenant' => $tenant,
            'plan' => $tenant->plan,
            'subscription' => $subscription,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'usage' => $limits->usage($tenant) + ['messages' => $quota->used($tenant)],
            'messageLimit' => $quota->limit($tenant),
            'invoices' => SubscriptionInvoice::query()->latest('id')->limit(12)->get(),
            'hasPending' => SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::Pending->value)->exists(),
            'pendingRequest' => Subscription::query()->pending()->with('plan')->latest('id')->first(),
            'trialUsed' => Subscription::query()->whereNotNull('trial_ends_at')->exists(),
            'transfer' => config('kostra.subscription'),
            'trialDays' => (int) config('kostra.trial_days'),
        ]);
    }

    public function trial(SubscriptionPlanRequest $request, TenantContext $context, StartTrial $trial): RedirectResponse
    {
        $plan = $request->plan();

        $trial($context->current(), $plan);

        return redirect()->route('owner.subscription.show')
            ->with('status', "Masa percobaan paket {$plan->name} dimulai. Berlaku ".config('kostra.trial_days').' hari; setelah itu kembali ke paket Gratis kecuali Anda berlangganan.');
    }

    public function subscribe(SubscriptionPlanRequest $request, TenantContext $context, RequestSubscription $subscribe): RedirectResponse
    {
        $plan = $request->plan();

        $invoice = $subscribe($context->current(), $plan, $request->period());

        return redirect()->route('owner.subscription.show')
            ->with('status', "Tagihan {$invoice->number} untuk paket {$plan->name} sudah dibuat. Paket aktif setelah transfer dicatat; bayar sebelum ".$invoice->due_date->translatedFormat('j F Y').'.');
    }

    public function downgrade(SubscriptionPlanRequest $request, TenantContext $context, DowngradePlan $downgrade): RedirectResponse
    {
        $plan = $request->plan();

        $downgrade($context->current(), $plan);

        return redirect()->route('owner.subscription.show')->with('status', "Paket diturunkan ke {$plan->name}.");
    }
}
