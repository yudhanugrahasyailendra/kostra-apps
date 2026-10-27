<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends PortalController
{
    public function __invoke(Request $request, string $token): View
    {
        $resident = $this->resident($request);

        $invoices = Invoice::query()
            ->where('resident_id', $resident->getKey())
            ->whereIn('status', InvoiceStatus::openValues())
            ->whereColumn('total', '>', 'paid_total')
            ->with('lease.room:id,number')
            ->orderBy('due_date')->orderBy('id')
            ->get();

        return view('portal.home', [
            'resident' => $resident,
            'invoices' => $invoices,
            'outstanding' => (int) $invoices->sum(fn (Invoice $invoice) => $invoice->remaining()),
            'lease' => $resident->leases()->active()->with(['room:id,number', 'property:id,name'])->latest('start_date')->first(),
            'pendingProofs' => PaymentProof::query()->where('resident_id', $resident->getKey())->pending()->count(),
            'openComplaints' => Complaint::query()->where('resident_id', $resident->getKey())->open()->count(),
            'today' => $this->tenant($request)->today(),
        ]);
    }
}
