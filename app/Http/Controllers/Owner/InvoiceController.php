<?php

namespace App\Http\Controllers\Owner;

use App\Actions\AddInvoiceAdjustment;
use App\Actions\CreateManualInvoice;
use App\Actions\VoidInvoice;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AdjustInvoiceRequest;
use App\Http\Requests\Owner\ManualInvoiceRequest;
use App\Http\Requests\Owner\VoidInvoiceRequest;
use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentProofResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Services\Billing\BillingClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,'.implode(',', array_column(InvoiceStatus::cases(), 'value'))],
            'property_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        // Tanpa parameter `period`, daftar membuka bulan berjalan; periode kosong berarti semua periode.
        $period = $request->has('period') ? ($filters['period'] ?? null) : BillingClock::today()->format('Y-m');
        $status = $filters['status'] ?? 'all';
        $q = trim($filters['q'] ?? '');
        $propertyId = $filters['property_id'] ?? null;

        $base = Invoice::query()
            ->when($propertyId, fn (Builder $query) => $query->where('property_id', $propertyId))
            ->when($period, fn (Builder $query) => $query->where('period', $period))
            ->when($q !== '', function (Builder $query) use ($q) {
                $like = '%'.addcslashes($q, '\\%_').'%';

                $query->where(fn ($where) => $where
                    ->where('number', 'like', $like)
                    ->orWhereHas('resident', fn ($resident) => $resident->where('name', 'like', $like))
                    ->orWhereHas('lease.room', fn ($room) => $room->where('number', 'like', $like)));
            });

        $invoices = (clone $base)
            ->with(['property', 'lease.room', 'resident'])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as c, coalesce(sum(total), 0) as total_sum, coalesce(sum(paid_total), 0) as paid_sum')
            ->groupBy('status')->get()->keyBy(fn ($row) => $row->status->value);

        $sum = fn (array $statuses, string $column) => (int) collect($statuses)->sum(fn (string $s) => $byStatus[$s]->{$column} ?? 0);
        $billable = array_map(fn (InvoiceStatus $s) => $s->value, [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Paid, InvoiceStatus::Overdue]);

        return Inertia::render('Owner/Invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices->items())->resolve(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
                'prev_url' => $invoices->previousPageUrl(),
                'next_url' => $invoices->nextPageUrl(),
            ],
            'filters' => ['q' => $q === '' ? null : $q, 'status' => $status, 'property_id' => $propertyId, 'period' => $period],
            'counts' => ['all' => (int) $byStatus->sum('c')] + collect(InvoiceStatus::cases())
                ->mapWithKeys(fn (InvoiceStatus $s) => [$s->value => (int) ($byStatus[$s->value]->c ?? 0)])->all(),
            'summary' => [
                'billed' => $sum($billable, 'total_sum'),
                'collected' => $sum($billable, 'paid_sum'),
                'outstanding' => $sum($billable, 'total_sum') - $sum($billable, 'paid_sum'),
                'overdue_amount' => $sum(['overdue'], 'total_sum') - $sum(['overdue'], 'paid_sum'),
                'overdue_count' => (int) ($byStatus['overdue']->c ?? 0),
            ],
            'properties' => Property::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'can' => ['create' => $request->user()->can('create', Invoice::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        $leases = Lease::query()->active()->with(['room', 'property', 'primaryResident'])->orderBy('property_id')->orderBy('id')->get();
        $today = BillingClock::today();

        return Inertia::render('Owner/Invoices/Create', [
            'leases' => $leases->map(fn (Lease $lease) => [
                'id' => $lease->id,
                'label' => 'Kamar '.$lease->room->number.' - '.$lease->primaryResident->name.' ('.$lease->property->name.')',
            ])->values(),
            'itemTypes' => array_map(fn (InvoiceItemType $t) => ['value' => $t->value, 'label' => $t->label()], InvoiceItemType::cases()),
            'defaults' => [
                'lease_id' => $request->integer('lease_id') ?: null,
                'due_date' => $today->addDays(7)->toDateString(),
                'today' => $today->toDateString(),
            ],
        ]);
    }

    public function store(ManualInvoiceRequest $request, CreateManualInvoice $create): RedirectResponse
    {
        $lease = Lease::query()->findOrFail($request->integer('lease_id'));

        $invoice = $create->handle($lease, $request->validated(), $request->user());

        return redirect()->route('owner.invoices.show', $invoice)->with('success', 'Tagihan manual '.$invoice->number.' diterbitkan.');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['property', 'lease.room', 'resident', 'items', 'adjustments.creator']);

        // Riwayat pembayaran tagihan ini (termasuk yang dibatalkan) lewat alokasinya.
        $payments = Payment::query()
            ->whereHas('allocations', fn ($allocation) => $allocation->where('invoice_id', $invoice->id))
            ->with(['resident', 'account', 'recorder', 'allocations.invoice'])
            ->orderByDesc('paid_on')->orderByDesc('id')->get();

        $proofs = PaymentProof::query()->where('invoice_id', $invoice->id)
            ->with(['invoice.lease.room', 'resident', 'attachments'])->orderByDesc('id')->get();

        return Inertia::render('Owner/Invoices/Show', [
            'invoice' => (new InvoiceDetailResource($invoice))->resolve(),
            'payments' => PaymentResource::collection($payments)->resolve(),
            'proofs' => PaymentProofResource::collection($proofs)->resolve(),
            'can_pay' => $invoice->status->isOpen() && $request->user()->can('create', Payment::class),
            'can_submit_proof' => $invoice->status->isOpen() && $request->user()->can('create', PaymentProof::class),
        ]);
    }

    public function adjust(AdjustInvoiceRequest $request, Invoice $invoice, AddInvoiceAdjustment $adjust): RedirectResponse
    {
        $adjust->handle($invoice, $request->integer('amount'), $request->string('reason')->toString(), $request->user());

        return back()->with('success', 'Penyesuaian dicatat dan total tagihan dihitung ulang.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, VoidInvoice $void): RedirectResponse
    {
        $void->handle($invoice, $request->string('reason')->toString());

        return back()->with('success', 'Tagihan dibatalkan.');
    }
}
