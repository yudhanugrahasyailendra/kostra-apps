<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Actions\Subscriptions\VoidSubscriptionInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaySubscriptionInvoiceRequest;
use App\Models\SubscriptionInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionInvoiceController extends Controller
{
    public function pay(PaySubscriptionInvoiceRequest $request, SubscriptionInvoice $invoice, RecordSubscriptionPayment $record): RedirectResponse
    {
        $paidOn = $request->validated('paid_on');

        $record(
            $invoice,
            $request->validated('payment_method'),
            $request->validated('reference'),
            $paidOn === null ? null : CarbonImmutable::createFromFormat('!Y-m-d', $paidOn),
            $request->user(),
        );

        return redirect()->route('admin.tenants.show', $invoice->tenant_id)
            ->with('status', "Pembayaran {$invoice->number} dicatat. Langganan aktif.");
    }

    public function void(Request $request, SubscriptionInvoice $invoice, VoidSubscriptionInvoice $void): RedirectResponse
    {
        Gate::authorize('platform.manage');

        $void($invoice);

        return redirect()->route('admin.tenants.show', $invoice->tenant_id)->with('status', "Tagihan {$invoice->number} dibatalkan.");
    }
}
