<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Resources\Portal\PortalInvoiceResource;
use App\Http\Resources\Portal\PortalPaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Riwayat tagihan lunas dan kuitansi pembayaran milik penghuni. */
class HistoryController extends PortalController
{
    private const LIMIT = 36;

    public function __invoke(Request $request): Response
    {
        $resident = $this->resident($request);

        $invoices = Invoice::query()->where('resident_id', $resident->id)
            ->where('status', InvoiceStatus::Paid->value)
            ->orderByDesc('due_date')->orderByDesc('id')->limit(self::LIMIT)->get();

        $payments = Payment::query()->where('resident_id', $resident->id)
            ->where('status', PaymentStatus::Active->value)
            ->with('allocations.invoice')
            ->orderByDesc('paid_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return Inertia::render('Portal/History', $this->shell($request) + [
            'invoices' => PortalInvoiceResource::collection($invoices)->resolve(),
            'payments' => PortalPaymentResource::collection($payments)->resolve(),
        ]);
    }
}
