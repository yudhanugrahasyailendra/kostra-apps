<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Payments\SubmitPaymentProof;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Portal\ProofRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;

class ProofController extends PortalController
{
    /**
     * Unggah bukti transfer: masuk antrean verifikasi (pending). Tagihan baru berubah setelah pemilik menyetujui.
     */
    public function store(ProofRequest $request, string $token, int $invoice, SubmitPaymentProof $submit): RedirectResponse
    {
        $resident = $this->resident($request);

        $invoice = Invoice::query()
            ->where('resident_id', $resident->getKey())
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value])
            ->find($invoice) ?? abort(404);

        $page = route('portal.invoices.show', ['token' => $token, 'invoice' => $invoice->getKey()]);

        $this->orRedirect($page, fn () => $submit($invoice, $request->safe()->only(['amount', 'paid_on', 'sender_note']), $request->file('file'), $resident));

        return redirect($page)
            ->with('status', 'Bukti transfer terkirim. Pengelola akan memeriksanya; tagihan baru berubah setelah disetujui.');
    }
}
