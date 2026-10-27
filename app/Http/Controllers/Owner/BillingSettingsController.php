<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BillingSettingsRequest;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BillingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('owner.settings.billing', ['settings' => BillingSetting::current()]);
    }

    public function update(BillingSettingsRequest $request): RedirectResponse
    {
        BillingSetting::current()->update($request->validated());

        return redirect()->route('owner.settings.billing')->with('status', 'Pengaturan penagihan disimpan. Berlaku untuk tagihan yang terbit berikutnya.');
    }
}
