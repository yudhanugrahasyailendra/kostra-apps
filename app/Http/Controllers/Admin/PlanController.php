<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        Gate::authorize('platform.manage');

        return view('admin.plans.index', [
            'plans' => Plan::query()->withCount('tenants')->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Plan $plan): View
    {
        Gate::authorize('platform.manage');

        return view('admin.plans.edit', ['plan' => $plan, 'features' => config('kostra.features')]);
    }

    /**
     * Harga baru hanya berlaku untuk tagihan langganan berikutnya (tagihan yang sudah terbit tidak berubah).
     * Batas dan kuota berlaku seketika bagi semua tenant di paket ini, karena tenant merujuk paketnya.
     * Perubahan dicatat di audit sebagai kejadian tingkat platform (tanpa tenant).
     */
    public function update(PlanRequest $request, Plan $plan, RecordAuditLog $audit): RedirectResponse
    {
        $plan->fill($request->validated());
        $changes = $plan->getDirty();
        $old = array_intersect_key($plan->getOriginal(), $changes);

        $plan->save();

        if ($changes !== []) {
            $audit(AuditEvent::Updated, $plan, $old, $changes);
        }

        return redirect()->route('admin.plans.index')->with('status', "Paket {$plan->name} diperbarui.");
    }
}
