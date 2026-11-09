<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BillingSettingsRequest;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingsController extends Controller
{
    public function show(): Response
    {
        $this->authorize('manage-settings');

        $settings = BillingSetting::current();

        return Inertia::render('Owner/Settings/Billing', [
            'settings' => [
                'invoice_prefix' => $settings->invoice_prefix,
                'issue_day' => $settings->issue_day,
                'due_day' => $settings->due_day,
                'prorate_first_month' => $settings->prorate_first_month,
                'prorate_last_month' => $settings->prorate_last_month,
            ],
        ]);
    }

    public function update(BillingSettingsRequest $request): RedirectResponse
    {
        BillingSetting::current()->update($request->validated());

        return back()->with('success', 'Pengaturan penagihan disimpan. Berlaku untuk tagihan berikutnya.');
    }
}
