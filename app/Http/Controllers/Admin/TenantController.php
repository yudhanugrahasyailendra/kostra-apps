<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ActivateTenant;
use App\Actions\SuspendTenant;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionInvoiceResource;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\TenantOverview;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request, TenantOverview $overview): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_column(TenantStatus::cases(), 'value'))],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));

        $tenants = Tenant::query()
            ->with('plan')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                // Satu kolom pencarian: nama kos, slug, telepon kos, atau email/telepon pemilik.
                // Alasan unscoped: pencarian lintas tenant oleh Super Admin lewat kontak pemilik. Dieksekusi di dalam
                // unscoped() (pluck), karena scope tenant diterapkan saat query dibangun, bukan saat dibuat.
                $ownerTenantIds = TenantContext::instance()->unscoped(
                    fn () => User::query()->where(fn ($u) => $u->where('email', 'like', $like)->orWhere('phone', 'like', $like))
                        ->whereNotNull('tenant_id')->pluck('tenant_id')
                );

                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhereIn('id', $ownerTenantIds));
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $rows = $overview->rows($tenants->getCollection());

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => array_values($rows),
            'pagination' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'total' => $tenants->total(),
                'prev_url' => $tenants->previousPageUrl(),
                'next_url' => $tenants->nextPageUrl(),
            ],
            'filters' => ['q' => $term, 'status' => $filters['status'] ?? null],
            'statusOptions' => array_map(fn (TenantStatus $s) => ['value' => $s->value, 'label' => $s->label()], TenantStatus::cases()),
        ]);
    }

    public function show(Tenant $tenant, TenantOverview $overview): Response
    {
        $this->authorize('view', $tenant);

        $tenant->load('plan');

        $invoices = TenantContext::instance()->runAs($tenant, fn () => SubscriptionInvoice::query()->latest('id')->limit(50)->get());

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $overview->rows(collect([$tenant]))[$tenant->id],
            'invoices' => SubscriptionInvoiceResource::collection($invoices)->resolve(),
        ]);
    }

    public function suspend(Tenant $tenant, SuspendTenant $suspend): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $suspend->handle($tenant);

        return back()->with('success', "Tenant {$tenant->name} ditangguhkan. Pemilik hanya bisa membaca dan mengekspor data.");
    }

    public function activate(Tenant $tenant, ActivateTenant $activate): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $activate->handle($tenant);

        return back()->with('success', "Tenant {$tenant->name} diaktifkan kembali.");
    }
}
