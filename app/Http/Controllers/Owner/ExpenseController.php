<?php

namespace App\Http\Controllers\Owner;

use App\Actions\EnsureDefaultExpenseCategories;
use App\Actions\SaveExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Services\Billing\BillingClock;
use App\Services\Reports\ExpenseReport;
use App\Services\Reports\ReportPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, ExpenseReport $report): Response
    {
        $this->authorize('viewAny', Expense::class);

        $tenantId = $request->user()->tenant_id;
        $filters = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
        ]);

        // Tenant lama (sebelum Fase 7) belum punya kategori bawaan; dibuat sekali saat halaman dibuka.
        app(EnsureDefaultExpenseCategories::class)->handle();

        $period = ReportPeriod::month($filters['period'] ?? BillingClock::today()->format('Y-m'));
        $propertyId = isset($filters['property_id']) ? (int) $filters['property_id'] : null;
        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : null;

        $expenses = $report->query($period, $propertyId, $categoryId)
            ->with(['category', 'property', 'creator', 'attachments'])
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Owner/Expenses/Index', [
            'expenses' => ExpenseResource::collection($expenses->items())->resolve(),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'total' => $expenses->total(),
                'prev_url' => $expenses->previousPageUrl(),
                'next_url' => $expenses->nextPageUrl(),
            ],
            'filters' => ['period' => $period->key(), 'property_id' => $propertyId, 'category_id' => $categoryId],
            'total' => $report->total($period, $propertyId, $categoryId),
            'categories' => ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (ExpenseCategory $c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Property $p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'today' => BillingClock::today()->toDateString(),
            'can' => ['create' => $request->user()->can('create', Expense::class)],
        ]);
    }

    public function store(ExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user(), null, $request->file('receipt'));

        return back()->with('success', 'Pengeluaran dicatat.');
    }

    public function update(ExpenseRequest $request, Expense $expense, SaveExpense $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user(), $expense, $request->file('receipt'));

        return back()->with('success', 'Pengeluaran diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return back()->with('success', 'Pengeluaran dihapus.');
    }
}
