<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Subscriptions\ActivateTenant;
use App\Actions\Subscriptions\SetTenantPlan;
use App\Actions\Subscriptions\SuspendTenant;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantIndexRequest;
use App\Http\Requests\Admin\TenantPlanRequest;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Messaging\MessageQuota;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(TenantIndexRequest $request): View
    {
        $q = trim((string) $request->validated('q'));

        $tenants = Tenant::query()
            ->with('plan:id,name')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                // Satu kotak cari: nama usaha, identitas internal, atau nama, email, dan nomor pemiliknya.
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereIn('id', User::query()->where('role', UserRole::Owner->value)
                        ->where(fn ($owner) => $owner->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                        ->select('tenant_id')));
            })
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->validated('plan_id'), fn ($query, $plan) => $query->where('plan_id', $plan))
            ->orderBy('name')->orderBy('id')
            ->paginate(25)->withQueryString();

        $ids = $tenants->pluck('id');
        $periods = $tenants->mapWithKeys(fn (Tenant $t) => [$t->id => $t->today()->format('Y-m')]);

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'q' => $q,
            'status' => $request->validated('status'),
            'planId' => $request->validated('plan_id'),
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => TenantStatus::cases(),
            // Pemakaian untuk satu halaman dihitung dengan beberapa query berkelompok, bukan satu per tenant.
            'properties' => Property::query()->whereIn('tenant_id', $ids)->selectRaw('tenant_id, COUNT(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id'),
            'rooms' => Room::query()->whereIn('tenant_id', $ids)->selectRaw('tenant_id, COUNT(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id'),
            'messages' => UsageCounter::query()->whereIn('tenant_id', $ids)->where('metric', MessageQuota::METRIC)
                ->whereIn('period', $periods->unique()->values())->get()
                ->filter(fn (UsageCounter $c) => $c->period === $periods[$c->tenant_id])->pluck('used', 'tenant_id'),
            'subscriptions' => Subscription::query()->whereIn('tenant_id', $ids)->current()->orderBy('id')->get()->keyBy('tenant_id'),
        ]);
    }

    public function show(Tenant $tenant, TenantContext $context, PlanLimits $limits, MessageQuota $quota): View
    {
        Gate::authorize('platform.manage');

        $tenant->load('plan');

        // Pemakaian dihitung dalam konteks tenant itu; panel admin sendiri lintas tenant.
        $usage = $context->run($tenant, fn () => $limits->usage($tenant) + ['messages' => $quota->used($tenant)]);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'owner' => User::query()->where('tenant_id', $tenant->getKey())->where('role', UserRole::Owner->value)->first(),
            'subscription' => Subscription::query()->where('tenant_id', $tenant->getKey())->current()->with('plan')->latest('id')->first(),
            'history' => Subscription::query()->where('tenant_id', $tenant->getKey())->with('plan:id,name')->latest('id')->limit(10)->get(),
            'invoices' => SubscriptionInvoice::query()->where('tenant_id', $tenant->getKey())->latest('id')->limit(24)->get(),
            'usage' => $usage,
            'messageLimit' => $quota->limit($tenant),
            'plans' => Plan::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function suspend(Tenant $tenant, SuspendTenant $suspend): RedirectResponse
    {
        Gate::authorize('platform.manage');

        $suspend($tenant);

        return redirect()->route('admin.tenants.show', $tenant)->with('status', 'Tenant ditangguhkan. Pemilik hanya bisa melihat dan mengekspor data.');
    }

    public function activate(Tenant $tenant, ActivateTenant $activate): RedirectResponse
    {
        Gate::authorize('platform.manage');

        $activate($tenant);

        return redirect()->route('admin.tenants.show', $tenant)->with('status', 'Tenant diaktifkan kembali.');
    }

    public function plan(TenantPlanRequest $request, Tenant $tenant, SetTenantPlan $setPlan): RedirectResponse
    {
        $plan = $request->plan();

        $setPlan($tenant, $plan, $request->period(), $request->boolean('ignore_limits'));

        return redirect()->route('admin.tenants.show', $tenant)->with('status', "Paket tenant ditetapkan ke {$plan->name}.");
    }
}
