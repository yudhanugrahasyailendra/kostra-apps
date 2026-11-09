<?php

namespace App\Http\Controllers\Owner;

use App\Actions\RecordPayment;
use App\Actions\VoidPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RecordPaymentRequest;
use App\Http\Requests\Owner\VoidPaymentRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Resident;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,'.implode(',', array_column(PaymentStatus::cases(), 'value'))],
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        // Seperti daftar tagihan: tanpa parameter `period` daftar membuka bulan berjalan; kosong berarti semua.
        $period = $request->has('period') ? ($filters['period'] ?? null) : BillingClock::today()->format('Y-m');
        $status = $filters['status'] ?? 'all';
        $q = trim($filters['q'] ?? '');

        $base = Payment::query()
            ->when($period, fn (Builder $query) => $query->whereBetween('paid_on', [
                CarbonImmutable::parse($period.'-01')->startOfMonth()->toDateString(),
                CarbonImmutable::parse($period.'-01')->endOfMonth()->toDateString(),
            ]))
            ->when($q !== '', function (Builder $query) use ($q) {
                $like = '%'.addcslashes($q, '\\%_').'%';

                $query->where(fn ($where) => $where
                    ->where('receipt_number', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('resident', fn ($resident) => $resident->where('name', 'like', $like)));
            });

        $payments = (clone $base)
            ->with(['resident', 'account', 'recorder', 'allocations.invoice'])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as c, coalesce(sum(amount), 0) as amount_sum')
            ->groupBy('status')->get()->keyBy(fn ($row) => $row->status->value);

        return Inertia::render('Owner/Payments/Index', [
            'payments' => PaymentResource::collection($payments->items())->resolve(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
                'prev_url' => $payments->previousPageUrl(),
                'next_url' => $payments->nextPageUrl(),
            ],
            'filters' => ['q' => $q === '' ? null : $q, 'status' => $status, 'period' => $period],
            'counts' => [
                'all' => (int) $byStatus->sum('c'),
                'active' => (int) ($byStatus['active']->c ?? 0),
                'voided' => (int) ($byStatus['voided']->c ?? 0),
            ],
            'summary' => [
                'received' => (int) ($byStatus['active']->amount_sum ?? 0),
                'count' => (int) ($byStatus['active']->c ?? 0),
            ],
            'can' => ['create' => $request->user()->can('create', Payment::class)],
        ]);
    }

    /**
     * Formulir catat pembayaran. Penghuni dipilih dari yang punya tagihan terbuka; tagihan terbuka
     * penghuni terpilih ditampilkan untuk dialokasikan (satu atau beberapa sekaligus).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Payment::class);

        $request->validate(['resident_id' => ['nullable', 'integer'], 'invoice_id' => ['nullable', 'integer']]);

        $preselected = $request->integer('invoice_id') ? Invoice::query()->open()->find($request->integer('invoice_id')) : null;
        $residentId = $preselected?->resident_id ?? ($request->integer('resident_id') ?: null);

        $residentIds = Invoice::query()->open()->distinct()->pluck('resident_id');
        $residents = Resident::query()->whereIn('id', $residentIds)->orderBy('name')->get(['id', 'name']);

        if ($residentId !== null && ! $residents->contains('id', $residentId)) {
            $residentId = null;
        }

        $invoices = $residentId === null ? collect() : Invoice::query()->open()
            ->where('resident_id', $residentId)->with('lease.room')->orderBy('due_date')->orderBy('id')->get();

        $accounts = PaymentAccount::query()->active()->orderByDesc('is_default')->orderBy('id')->get();

        return Inertia::render('Owner/Payments/Create', [
            'residents' => $residents->map(fn (Resident $r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'resident_id' => $residentId,
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'period' => $invoice->period,
                'room_number' => $invoice->lease?->room?->number,
                'due_date' => $invoice->due_date->toDateString(),
                'status_label' => $invoice->status->label(),
                'status_tone' => $invoice->status->tone(),
                'total' => $invoice->total,
                'paid_total' => $invoice->paid_total,
                'outstanding' => $invoice->outstanding(),
            ])->values(),
            'accounts' => PaymentAccountResource::collection($accounts)->resolve(),
            'methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'defaults' => [
                'invoice_id' => $preselected?->id,
                'paid_on' => BillingClock::today()->toDateString(),
                'payment_account_id' => $accounts->firstWhere('is_default', true)?->id,
            ],
        ]);
    }

    public function store(RecordPaymentRequest $request, RecordPayment $record): RedirectResponse
    {
        $resident = Resident::query()->findOrFail($request->integer('resident_id'));

        $allocations = [];
        foreach ($request->validated('allocations') as $row) {
            $allocations[(int) $row['invoice_id']] = (int) $row['amount'];
        }

        $payment = $record->handle(
            $resident,
            $request->integer('amount'),
            CarbonImmutable::parse($request->validated('paid_on')),
            PaymentMethod::from($request->validated('method')),
            $allocations,
            $request->user(),
            $request->filled('payment_account_id') ? $request->integer('payment_account_id') : null,
            $request->validated('reference'),
            $request->validated('notes'),
        );

        return redirect()->route('owner.payments.receipt', $payment)->with('success', 'Pembayaran dicatat. Kuitansi '.$payment->receipt_number.' terbit.');
    }

    public function void(VoidPaymentRequest $request, Payment $payment, VoidPayment $void): RedirectResponse
    {
        $void->handle($payment, $request->string('reason')->toString(), $request->user());

        return back()->with('success', 'Pembayaran dibatalkan dan tagihan terkait dihitung ulang.');
    }

    /** Halaman kuitansi; PDF diunduh lewat {@see receiptPdf()}. */
    public function receipt(Request $request, Payment $payment): Response
    {
        $this->authorize('view', $payment);

        return Inertia::render('Owner/Payments/Receipt', [
            'receipt' => $this->receiptData($payment),
            'can' => ['void' => $payment->isActive() && $request->user()->can('void', $payment)],
            'payment_id' => $payment->id,
        ]);
    }

    /** Unduh kuitansi sebagai PDF (dompdf). Memakai data yang sama dengan halaman kuitansi. */
    public function receiptPdf(Payment $payment): HttpResponse
    {
        $this->authorize('view', $payment);

        $receipt = $this->receiptData($payment);
        $filename = 'kuitansi-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($receipt['number'] ?? $payment->id)).'.pdf';

        return Pdf::loadView('pdf.receipt', ['receipt' => $receipt])
            ->setPaper('a5', 'landscape')
            ->download($filename);
    }

    /** @return array<string, mixed> */
    private function receiptData(Payment $payment): array
    {
        $payment->load(['resident', 'account', 'recorder', 'allocations.invoice.lease.room', 'allocations.invoice.property']);

        $firstInvoice = $payment->allocations->first()?->invoice;
        $tenant = TenantContext::instance()->tenant();

        return [
            'number' => $payment->receipt_number,
            'issued_at' => $payment->receipt_issued_at?->toIso8601String(),
            'business_name' => $tenant?->name,
            'property_name' => $firstInvoice?->property?->name,
            'property_address' => $firstInvoice?->property?->address,
            'resident_name' => $payment->resident->name,
            'room_number' => $firstInvoice?->lease?->room?->number,
            'amount' => $payment->amount,
            'paid_on' => $payment->paid_on->toDateString(),
            'method_label' => $payment->method->label(),
            'reference' => $payment->reference,
            'account_label' => $payment->account?->displayName(),
            'account_name' => $payment->account?->account_name,
            'recorded_by_name' => $payment->recorder?->name,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'void_reason' => $payment->void_reason,
            'lines' => $payment->allocations->map(fn ($allocation) => [
                'invoice_number' => $allocation->invoice?->number,
                'period' => $allocation->invoice?->period,
                'amount' => $allocation->amount,
                'invoice_total' => $allocation->invoice?->total,
                'invoice_paid' => $allocation->invoice?->status === InvoiceStatus::Paid,
            ])->values()->all(),
        ];
    }
}
