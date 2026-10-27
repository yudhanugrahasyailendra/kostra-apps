<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PaymentController extends PortalController
{
    public function index(Request $request, string $token): View
    {
        return view('portal.payments.index', [
            'payments' => $this->ownPayments($request)
                ->with('allocations.invoice:id,number')
                ->latest('paid_on')->latest('id')
                ->paginate(10)->withQueryString(),
        ]);
    }

    /**
     * Kuitansi hanya ada untuk pembayaran aktif milik penghuni; yang dibatalkan atau milik orang lain tidak ditemukan.
     */
    public function receipt(Request $request, string $token, int $payment): View
    {
        $payment = $this->findPayment($request, $payment);

        return view('portal.payments.receipt', ['payment' => $payment, 'tenant' => $this->tenant($request)]);
    }

    public function pdf(Request $request, string $token, int $payment): Response
    {
        $payment = $this->findPayment($request, $payment);

        return Pdf::loadView('owner.payments.receipt-pdf', ['payment' => $payment, 'tenant' => $this->tenant($request)])
            ->setPaper('a5', 'portrait')
            ->download($payment->receipt_number.'.pdf');
    }

    private function findPayment(Request $request, int $id): Payment
    {
        return $this->ownPayments($request)
            ->with(['resident:id,name', 'account', 'allocations.invoice.items', 'recorder:id,name'])
            ->find($id) ?? abort(404);
    }

    private function ownPayments(Request $request)
    {
        return Payment::query()
            ->where('resident_id', $this->resident($request)->getKey())
            ->where('status', PaymentStatus::Active->value);
    }
}
