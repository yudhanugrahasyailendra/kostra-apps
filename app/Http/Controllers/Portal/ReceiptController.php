<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\ReceiptData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends PortalController
{
    public function show(Request $request, string $token, int $payment, ReceiptData $data): Response
    {
        return Inertia::render('Portal/Receipt', $this->shell($request) + [
            'receipt' => $this->data($request, $payment, $data),
            'payment_id' => $payment,
        ]);
    }

    public function pdf(Request $request, string $token, int $payment, ReceiptData $data): HttpResponse
    {
        $receipt = $this->data($request, $payment, $data);
        $filename = 'kuitansi-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($receipt['number'] ?? $payment)).'.pdf';

        return Pdf::loadView('pdf.receipt', ['receipt' => $receipt])->setPaper('a5', 'landscape')->download($filename);
    }

    /**
     * Kuitansi hanya untuk pembayaran aktif milik penghuni dari token (IDOR dicegah di sini). Nama pencatat
     * internal tidak ikut ke penghuni.
     *
     * @return array<string, mixed>
     */
    private function data(Request $request, int $id, ReceiptData $data): array
    {
        $payment = Payment::query()
            ->where('resident_id', $this->resident($request)->id)
            ->where('status', PaymentStatus::Active->value)
            ->findOrFail($id);

        return Arr::except($data->for($payment), ['recorded_by_name', 'void_reason']);
    }
}
