<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\VoidPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PaymentIndexRequest;
use App\Http\Requests\Owner\RecordPaymentRequest;
use App\Http\Requests\Owner\VoidPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Resident;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(PaymentIndexRequest $request, TenantContext $context): View
    {
        Gate::authorize('viewAny', Payment::class);

        $tenant = $context->current();
        $today = $tenant->today();
        $period = $request->validated('period') ?? $today->format('Y-m');
        $q = trim((string) $request->validated('q'));

        $base = Payment::query()
            ->when($period !== 'all', function ($query) use ($period, $tenant) {
                $start = CarbonImmutable::createFromFormat('!Y-m', $period, $tenant->timezone)->startOfMonth();
                $query->whereBetween('paid_on', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
            })
            ->when($request->validated('method'), fn ($query, $method) => $query->where('method', $method))
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('receipt_number', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhereHas('resident', fn ($r) => $r->where('name', 'like', $like));
                });
            });

        // Ringkasan uang: hanya pembayaran aktif yang dihitung sebagai pemasukan.
        $summary = (clone $base)->selectRaw(
            "COALESCE(SUM(CASE WHEN status = 'active' THEN amount END), 0) as received,
             SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
             SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) as voided_count"
        )->first();

        $payments = (clone $base)
            ->with(['resident:id,name', 'allocations.invoice:id,number'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('owner.payments.index', [
            'payments' => $payments,
            'period' => $period,
            'q' => $q,
            'method' => $request->validated('method'),
            'status' => $request->validated('status'),
            'summary' => [
                'received' => (int) $summary->received,
                'active' => (int) $summary->active_count,
                'voided' => (int) $summary->voided_count,
            ],
            'months' => collect(range(0, 11))->map(fn (int $i) => $today->startOfMonth()->subMonths($i)),
        ]);
    }

    public function create(Request $request, TenantContext $context): View
    {
        Gate::authorize('record', Payment::class);

        $invoice = $request->query('invoice_id') ? Invoice::query()->findOrFail((int) $request->query('invoice_id')) : null;
        $residentId = (int) ($request->query('resident_id') ?: $invoice?->resident_id);
        $resident = $residentId ? Resident::query()->findOrFail($residentId) : null;

        return view('owner.payments.create', [
            'resident' => $resident,
            'preselected' => $invoice?->getKey(),
            // Penghuni yang memiliki tagihan terbuka di kontraknya (langkah 1).
            'residents' => $resident ? collect() : Resident::query()
                ->whereHas('leases', fn ($lease) => $lease->whereHas('invoices', fn ($i) => $i->open()))
                ->orderBy('name')->get(['id', 'name', 'phone']),
            'invoices' => $resident
                ? Invoice::query()->open()
                    ->whereHas('lease.residents', fn ($r) => $r->whereKey($resident->getKey()))
                    ->with('lease.room:id,number')
                    ->orderBy('due_date')->orderBy('id')->get()
                : collect(),
            'accounts' => PaymentAccount::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->get(),
            'methods' => PaymentMethod::cases(),
            'today' => $context->current()->today()->toDateString(),
        ]);
    }

    public function store(RecordPaymentRequest $request, RecordPayment $record): RedirectResponse
    {
        $payment = $record($request->validated(), $request->user());

        return redirect()->route('owner.payments.show', $payment)
            ->with('status', 'Pembayaran dicatat. Kuitansi '.$payment->receipt_number.' sudah terbit.');
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['resident:id,name', 'account', 'allocations.invoice:id,number,total,status', 'recorder:id,name', 'voider:id,name']);

        return view('owner.payments.show', ['payment' => $payment]);
    }

    public function receipt(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['resident:id,name', 'account', 'allocations.invoice.items', 'recorder:id,name']);

        return view('owner.payments.receipt', ['payment' => $payment, 'tenant' => $payment->tenant]);
    }

    public function receiptPdf(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['resident:id,name', 'account', 'allocations.invoice.items', 'recorder:id,name']);

        return Pdf::loadView('owner.payments.receipt-pdf', ['payment' => $payment, 'tenant' => $payment->tenant])
            ->setPaper('a5', 'portrait')
            ->download($payment->receipt_number.'.pdf');
    }

    public function void(VoidPaymentRequest $request, Payment $payment, VoidPayment $void): RedirectResponse
    {
        $void($payment, $request->validated('reason'), $request->user());

        return redirect()->route('owner.payments.show', $payment)
            ->with('status', 'Pembayaran dibatalkan. Tagihan terkait dihitung ulang.');
    }
}
