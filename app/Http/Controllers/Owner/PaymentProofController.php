<?php

namespace App\Http\Controllers\Owner;

use App\Actions\ApprovePaymentProof;
use App\Actions\RejectPaymentProof;
use App\Actions\SubmitPaymentProof;
use App\Enums\PaymentMethod;
use App\Enums\ProofStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ApprovePaymentProofRequest;
use App\Http\Requests\Owner\RejectPaymentProofRequest;
use App\Http\Requests\Owner\SubmitPaymentProofRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Http\Resources\PaymentProofResource;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentProofController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PaymentProof::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:all,'.implode(',', array_column(ProofStatus::cases(), 'value'))],
        ]);

        // Antrean membuka bukti yang menunggu lebih dulu.
        $status = $filters['status'] ?? ProofStatus::Pending->value;

        $proofs = PaymentProof::query()
            ->with(['invoice.lease.room', 'resident', 'attachments'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        $counts = PaymentProof::query()->selectRaw('status, count(*) as c')->groupBy('status')->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->c]);

        $accounts = PaymentAccount::query()->active()->orderByDesc('is_default')->orderBy('id')->get();

        return Inertia::render('Owner/PaymentProofs/Index', [
            'proofs' => PaymentProofResource::collection($proofs->items())->resolve(),
            'pagination' => [
                'current_page' => $proofs->currentPage(),
                'last_page' => $proofs->lastPage(),
                'total' => $proofs->total(),
                'prev_url' => $proofs->previousPageUrl(),
                'next_url' => $proofs->nextPageUrl(),
            ],
            'filters' => ['status' => $status],
            'counts' => [
                'all' => (int) $counts->sum(),
                'pending' => $counts['pending'] ?? 0,
                'approved' => $counts['approved'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ],
            'accounts' => PaymentAccountResource::collection($accounts)->resolve(),
            'methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'default_account_id' => $accounts->firstWhere('is_default', true)?->id,
        ]);
    }

    public function store(SubmitPaymentProofRequest $request, Invoice $invoice, SubmitPaymentProof $submit): RedirectResponse
    {
        $submit->handle(
            $invoice,
            $request->integer('amount'),
            $request->validated('paid_on'),
            $request->validated('sender_note'),
            $request->file('file'),
            $request->user(),
        );

        return redirect()->route('owner.proofs.index')->with('success', 'Bukti transfer masuk antrean verifikasi.');
    }

    public function approve(ApprovePaymentProofRequest $request, PaymentProof $proof, ApprovePaymentProof $approve): RedirectResponse
    {
        $approve->handle(
            $proof,
            $request->user(),
            $request->filled('amount') ? $request->integer('amount') : null,
            PaymentMethod::from($request->validated('method')),
            $request->filled('payment_account_id') ? $request->integer('payment_account_id') : null,
            $request->validated('paid_on'),
        );

        return back()->with('success', 'Bukti disetujui. Pembayaran dicatat dan kuitansi terbit.');
    }

    public function reject(RejectPaymentProofRequest $request, PaymentProof $proof, RejectPaymentProof $reject): RedirectResponse
    {
        $reject->handle($proof, $request->string('reason')->toString(), $request->user());

        return back()->with('success', 'Bukti ditolak.');
    }
}
