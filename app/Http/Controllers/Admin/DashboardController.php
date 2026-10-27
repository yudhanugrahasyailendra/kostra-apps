<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('platform.manage');

        $byStatus = Tenant::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pending = SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::Pending->value);

        return view('admin.dashboard', [
            'tenants' => collect(TenantStatus::cases())->mapWithKeys(fn (TenantStatus $s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)])->all(),
            'paying' => Subscription::query()->where('status', SubscriptionStatus::Active->value)
                ->whereHas('plan', fn ($plan) => $plan->where('price_monthly', '>', 0))->count(),
            'trialing' => Subscription::query()->where('status', SubscriptionStatus::Trialing->value)->count(),
            'pastDue' => Subscription::query()->where('status', SubscriptionStatus::PastDue->value)->count(),
            'pendingCount' => (clone $pending)->count(),
            'pendingAmount' => (int) (clone $pending)->sum('amount'),
            'recentPending' => (clone $pending)->with('subscription.plan:id,name')->latest('id')->limit(8)->get(),
        ]);
    }
}
