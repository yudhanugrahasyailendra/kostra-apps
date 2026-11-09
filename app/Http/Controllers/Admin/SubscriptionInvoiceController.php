<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordSubscriptionPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordSubscriptionPaymentRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class SubscriptionInvoiceController extends Controller
{
    /** Catat transfer langganan. Tagihan dicari di dalam tenant di URL, jadi id tagihan tenant lain menghasilkan 404. */
    public function pay(RecordSubscriptionPaymentRequest $request, Tenant $tenant, int $invoice, RecordSubscriptionPayment $record): RedirectResponse
    {
        $record->handle($tenant, $invoice, $request->validated());

        return back()->with('success', 'Pembayaran langganan dicatat.');
    }
}
