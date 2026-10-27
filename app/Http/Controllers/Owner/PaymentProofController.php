<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Payments\ApprovePaymentProof;
use App\Actions\Payments\RejectPaymentProof;
use App\Actions\Payments\SubmitPaymentProof;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ApproveProofRequest;
use App\Http\Requests\Owner\RejectProofRequest;
use App\Http\Requests\Owner\SubmitProofRequest;
use App\Models\Invoice;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentProofController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PaymentProof::class);

        $tab = $request->validate(['tab' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])]])['tab'] ?? 'pending';

        $counts = PaymentProof::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $proofs = PaymentProof::query()
            ->with(['invoice:id,number,total,paid_total,status,lease_id', 'invoice.lease.room:id,number', 'resident:id,name', 'attachments', 'payment:id,receipt_number', 'reviewer:id,name'])
            ->when($tab !== 'all', fn ($query) => $query->where('status', $tab))
            ->orderByRaw("status = 'pending' DESC")
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('owner.proofs.index', [
            'proofs' => $proofs,
            'tab' => $tab,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'all' => (int) $counts->sum(),
            ],
        ]);
    }

    public function store(SubmitProofRequest $request, Invoice $invoice, SubmitPaymentProof $submit): RedirectResponse
    {
        $submit($invoice, $request->validated(), $request->file('file'), $request->user());

        return redirect()->route('owner.invoices.show', $invoice)
            ->with('status', 'Bukti transfer dicatat dan menunggu verifikasi. Tagihan belum berubah.');
    }

    public function approve(ApproveProofRequest $request, PaymentProof $proof, ApprovePaymentProof $approve): RedirectResponse
    {
        $payment = $approve($proof, $request->user(), $request->validated('amount'));

        return redirect()->route('owner.proofs.index')
            ->with('status', 'Bukti disetujui. Pembayaran dicatat dengan kuitansi '.$payment->receipt_number.'.');
    }

    public function reject(RejectProofRequest $request, PaymentProof $proof, RejectPaymentProof $reject): RedirectResponse
    {
        $reject($proof, $request->validated('reason'), $request->user());

        return redirect()->route('owner.proofs.index')->with('status', 'Bukti ditolak. Penghuni akan diberi tahu.');
    }
}
