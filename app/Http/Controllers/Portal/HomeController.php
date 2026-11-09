<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ProofStatus;
use App\Http\Resources\Portal\PortalInvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Beranda: tagihan yang masih harus dibayar dan ringkasan kontrak. */
class HomeController extends PortalController
{
    public function __invoke(Request $request): Response
    {
        $resident = $this->resident($request);

        $invoices = Invoice::query()->open()->where('resident_id', $resident->id)
            ->with('items')
            ->withExists(['proofs as has_pending_proof' => fn ($q) => $q->where('status', ProofStatus::Pending->value)])
            ->orderBy('due_date')->orderBy('id')
            ->get();

        $lease = $this->activeLease($resident);

        return Inertia::render('Portal/Home', $this->shell($request) + [
            'invoices' => PortalInvoiceResource::collection($invoices)->resolve(),
            'outstanding_total' => (int) $invoices->sum(fn (Invoice $i) => $i->outstanding()),
            'lease' => $lease === null ? null : [
                'end_date' => $lease->end_date?->toDateString(),
                'days_remaining' => $lease->daysRemaining(),
            ],
        ]);
    }
}
