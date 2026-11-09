<?php

namespace App\Http\Controllers\Portal;

use App\Actions\SubmitPaymentProof;
use App\Enums\InvoiceStatus;
use App\Enums\ProofStatus;
use App\Http\Requests\Portal\PortalProofRequest;
use App\Http\Resources\Portal\PortalInvoiceResource;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends PortalController
{
    /** Pending maksimal per tagihan, supaya antrean pemilik tidak dibanjiri unggahan berulang. */
    private const MAX_PENDING_PROOFS = 3;

    public function show(Request $request, string $token, int $invoice): Response
    {
        $model = $this->findInvoice($request, $invoice)->load(['items', 'proofs']);

        $accounts = PaymentAccount::query()->active()
            ->where(fn ($q) => $q->whereNull('property_id')->orWhere('property_id', $model->property_id))
            ->orderByDesc('is_default')->orderBy('id')->get();

        return Inertia::render('Portal/Invoice', $this->shell($request) + [
            'invoice' => PortalInvoiceResource::make($model)->resolve(),
            'accounts' => $accounts->map(fn (PaymentAccount $a) => [
                'id' => $a->id,
                'type_label' => $a->type->label(),
                'bank_name' => $a->bank_name,
                'account_number' => $a->account_number,
                'account_name' => $a->account_name,
                'instructions' => $a->instructions,
            ])->values(),
            'proofs' => $model->proofs->sortByDesc('id')->values()->map(fn ($proof) => [
                'id' => $proof->id,
                'amount' => $proof->amount,
                'status_label' => $proof->status->label(),
                'status_tone' => $proof->status->tone(),
                'reject_reason' => $proof->reject_reason,
                'created_at' => $proof->created_at->toIso8601String(),
            ]),
            'can_pay' => in_array($model->status, InvoiceStatus::open(), true) && ! $this->tenant()->isReadOnly(),
        ]);
    }

    /** Bukti masuk antrean verifikasi (pending). Tidak mengubah status atau saldo tagihan. */
    public function storeProof(PortalProofRequest $request, string $token, int $invoice, SubmitPaymentProof $submit): RedirectResponse
    {
        $model = $this->findInvoice($request, $invoice);

        $pending = $model->proofs()->where('status', ProofStatus::Pending->value)->count();

        if ($pending >= self::MAX_PENDING_PROOFS) {
            throw ValidationException::withMessages(['file' => 'Bukti sebelumnya masih menunggu verifikasi. Mohon tunggu pengelola memeriksanya.']);
        }

        $submit->handle(
            $model,
            $request->integer('amount'),
            $request->validated('paid_on'),
            $request->validated('sender_note'),
            $request->file('file'),
            null,
            $this->resident($request),
        );

        return back()->with('success', 'Bukti transfer terkirim. Pengelola akan memeriksanya.');
    }

    /** Tagihan hanya ditemukan bila milik penghuni dari token dan bukan draf atau batal (IDOR dicegah di sini). */
    private function findInvoice(Request $request, int $id): Invoice
    {
        return Invoice::query()
            ->where('resident_id', $this->resident($request)->id)
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value])
            ->findOrFail($id);
    }
}
