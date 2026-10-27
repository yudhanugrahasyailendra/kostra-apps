<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Finance\SaveExpense;
use App\Actions\Finance\SeedExpenseCategories;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ExpenseIndexRequest;
use App\Http\Requests\Owner\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Support\Dates;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(ExpenseIndexRequest $request, TenantContext $context): View
    {
        $tenant = $context->current();
        $today = $tenant->today();
        $period = $request->validated('period') ?? $today->format('Y-m');
        $q = trim((string) $request->validated('q'));

        $base = Expense::query()
            ->when($period !== 'all', function ($query) use ($period, $tenant) {
                $start = Dates::month($period, $tenant->timezone);
                $query->whereBetween('spent_on', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
            })
            ->when($request->validated('property_id'), fn ($query, $id) => $query->where('property_id', $id))
            ->when($request->validated('expense_category_id'), fn ($query, $id) => $query->where('expense_category_id', $id))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('description', 'like', $like)->orWhere('vendor_name', 'like', $like));
            });

        $summary = (clone $base)->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as items')->first();

        return view('owner.expenses.index', [
            'expenses' => (clone $base)
                ->with(['category:id,name', 'property:id,name', 'attachments:id,attachable_id,attachable_type,category'])
                ->orderByDesc('spent_on')->orderByDesc('id')
                ->paginate(50)->withQueryString(),
            'period' => $period,
            'q' => $q,
            'propertyId' => $request->validated('property_id'),
            'categoryId' => $request->validated('expense_category_id'),
            'summary' => ['total' => (int) $summary->total, 'items' => (int) $summary->items],
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'categories' => $this->categories(),
            'months' => collect(range(0, 11))->map(fn (int $i) => $today->startOfMonth()->subMonths($i)),
        ]);
    }

    public function create(TenantContext $context): View
    {
        Gate::authorize('create', Expense::class);

        return view('owner.expenses.form', [
            'expense' => null,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'categories' => $this->categories(),
            'today' => $context->current()->today()->toDateString(),
        ]);
    }

    public function store(ExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $save($request->validated(), $request->user(), $request->file('file'));

        return redirect()->route('owner.expenses.index')->with('status', 'Pengeluaran dicatat.');
    }

    public function edit(Expense $expense, TenantContext $context): View
    {
        Gate::authorize('update', $expense);

        return view('owner.expenses.form', [
            'expense' => $expense->load('attachments'),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'categories' => $this->categories(),
            'today' => $context->current()->today()->toDateString(),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense, SaveExpense $save): RedirectResponse
    {
        $save($request->validated(), $request->user(), $request->file('file'), $expense);

        return redirect()->route('owner.expenses.index')->with('status', 'Pengeluaran diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        // Soft delete: baris tetap ada dan tercatat di audit, tetapi keluar dari laporan.
        $expense->delete();

        return redirect()->route('owner.expenses.index')->with('status', 'Pengeluaran dihapus dari laporan.');
    }

    /**
     * Kategori bawaan dibuat saat pendaftaran; tenant lama yang belum punya dilengkapi di sini.
     */
    private function categories()
    {
        if (! ExpenseCategory::query()->exists()) {
            app(SeedExpenseCategories::class)();
        }

        return ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }
}
