<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends PortalController
{
    public function index(Request $request, string $token): View
    {
        $resident = $this->resident($request);

        return view('portal.invoices.index', [
            'invoices' => $this->visibleInvoices($resident->getKey())
                ->with('lease.room:id,number')
                ->latest('issue_date')->latest('id')
                ->paginate(10)->withQueryString(),
        ]);
    }

    public function show(Request $request, string $token, int $invoice): View
    {
        $resident = $this->resident($request);

        // Kepemilikan diperiksa di query: tagihan penghuni lain (atau yang batal) tidak ditemukan.
        $invoice = $this->visibleInvoices($resident->getKey())
            ->with(['items', 'lease.room:id,number', 'lease.property:id,name'])
            ->find($invoice) ?? abort(404);

        $canPay = in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue], true)
            && $invoice->remaining() > 0;

        return view('portal.invoices.show', [
            'invoice' => $invoice,
            'canPay' => $canPay,
            'accounts' => $canPay ? PaymentAccount::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('property_id')->orWhere('property_id', $invoice->property_id))
                ->with('qrisImage')
                ->orderByDesc('is_default')->orderBy('id')
                ->get() : collect(),
            'proofs' => PaymentProof::query()
                ->where('resident_id', $resident->getKey())
                ->where('invoice_id', $invoice->getKey())
                ->latest('id')->get(),
        ]);
    }

    private function visibleInvoices(int $residentId)
    {
        return Invoice::query()
            ->where('resident_id', $residentId)
            // Draf belum diterbitkan dan yang batal bukan tagihan bagi penghuni.
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value]);
    }
}
