<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Billing\AddInvoiceAdjustment;
use App\Actions\Billing\CreateManualInvoice;
use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AdjustInvoiceRequest;
use App\Http\Requests\Owner\InvoiceIndexRequest;
use App\Http\Requests\Owner\ManualInvoiceRequest;
use App\Http\Requests\Owner\VoidInvoiceRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /** Pemetaan tab ke status. */
    private const TABS = [
        'issued' => ['issued'],
        'partial' => ['partial'],
        'paid' => ['paid'],
        'overdue' => ['overdue'],
        'void' => ['void'],
    ];

    public function index(InvoiceIndexRequest $request, TenantContext $context): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $tenant = $context->current();
        $today = $tenant->today();
        $period = $request->validated('period') ?? $today->format('Y-m');
        $tab = $request->validated('tab') ?? 'all';
        $q = trim((string) $request->validated('q'));

        $base = Invoice::query()
            ->when($period !== 'all', function ($query) use ($period, $tenant) {
                $start = CarbonImmutable::createFromFormat('!Y-m', $period, $tenant->timezone)->startOfMonth();
                $query->whereBetween('issue_date', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
            })
            ->when($request->validated('property_id'), fn ($query, $id) => $query->where('property_id', $id))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('number', 'like', $like)
                        ->orWhereHas('resident', fn ($r) => $r->where('name', 'like', $like))
                        ->orWhereHas('lease.room', fn ($r) => $r->where('number', 'like', $like));
                });
            });

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // Ringkasan uang: satu query, semua integer rupiah (tagihan batal tidak dihitung).
        $summary = (clone $base)->selectRaw(
            "COALESCE(SUM(CASE WHEN status <> 'void' THEN total END), 0) as issued_total,
             COALESCE(SUM(CASE WHEN status <> 'void' THEN paid_total END), 0) as collected,
             COALESCE(SUM(CASE WHEN status = 'overdue' THEN CAST(total AS SIGNED) - CAST(paid_total AS SIGNED) END), 0) as overdue_amount"
        )->first();

        $invoices = (clone $base)
            ->with(['resident:id,name', 'lease:id,room_id', 'lease.room:id,number,floor', 'items'])
            ->when(isset(self::TABS[$tab]), fn ($query) => $query->whereIn('status', self::TABS[$tab]))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $months = collect(range(0, 11))->map(fn (int $i) => $today->startOfMonth()->subMonths($i));

        return view('owner.invoices.index', [
            'invoices' => $invoices,
            'tab' => $tab,
            'period' => $period,
            'q' => $q,
            'propertyId' => $request->validated('property_id'),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'all' => (int) $byStatus->sum(),
                'issued' => (int) ($byStatus['issued'] ?? 0),
                'partial' => (int) ($byStatus['partial'] ?? 0),
                'paid' => (int) ($byStatus['paid'] ?? 0),
                'overdue' => (int) ($byStatus['overdue'] ?? 0),
                'void' => (int) ($byStatus['void'] ?? 0),
            ],
            'summary' => [
                'issued' => (int) $summary->issued_total,
                'collected' => (int) $summary->collected,
                'overdue' => (int) $summary->overdue_amount,
            ],
            'months' => $months,
        ]);
    }

    public function create(Request $request, TenantContext $context): View
    {
        Gate::authorize('create', Invoice::class);

        $lease = Lease::query()->with(['room:id,number', 'primaryResident:id,name'])->findOrFail((int) $request->query('lease_id'));

        return view('owner.invoices.create', [
            'lease' => $lease,
            'today' => $context->current()->today(),
            'itemTypes' => InvoiceItemType::cases(),
            'rows' => 5,
        ]);
    }

    public function store(ManualInvoiceRequest $request, CreateManualInvoice $create): RedirectResponse
    {
        $lease = Lease::query()->findOrFail($request->validated('lease_id'));

        $invoice = $create($lease, $request->validated(), $request->user());

        return redirect()->route('owner.invoices.show', $invoice)->with('status', 'Tagihan diterbitkan dengan nomor '.$invoice->number.'.');
    }

    public function show(Invoice $invoice, TenantContext $context): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['items', 'adjustments.creator:id,name', 'lease.room:id,number', 'resident:id,name', 'property:id,name', 'creator:id,name', 'allocations.payment:id,receipt_number,paid_on,status,method', 'proofs.attachments']);

        return view('owner.invoices.show', [
            'invoice' => $invoice,
            'today' => $context->current()->today(),
        ]);
    }

    public function adjust(AdjustInvoiceRequest $request, Invoice $invoice, AddInvoiceAdjustment $adjust): RedirectResponse
    {
        $amount = $request->validated('amount') * ($request->validated('direction') === 'decrease' ? -1 : 1);

        $adjust($invoice, $amount, $request->validated('reason'), $request->user());

        return redirect()->route('owner.invoices.show', $invoice)->with('status', 'Penyesuaian dicatat. Jumlah item asli tidak berubah.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, VoidInvoice $void): RedirectResponse
    {
        $void($invoice, $request->validated('reason'));

        return redirect()->route('owner.invoices.show', $invoice)->with('status', 'Tagihan dibatalkan. Nomornya tetap tercatat.');
    }
}
