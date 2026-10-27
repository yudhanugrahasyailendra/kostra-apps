<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Attachments\StoreAttachment;
use App\Actions\Payments\SavePaymentAccount;
use App\Enums\AttachmentCategory;
use App\Enums\PaymentAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PaymentAccountRequest;
use App\Http\Requests\Owner\QrisImageRequest;
use App\Models\PaymentAccount;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentAccountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageAny', PaymentAccount::class);

        return view('owner.payment-accounts.index', [
            'accounts' => PaymentAccount::query()->with(['property:id,name', 'qrisImage'])->orderByDesc('is_active')->orderByDesc('is_default')->orderBy('id')->get(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'types' => PaymentAccountType::cases(),
        ]);
    }

    public function store(PaymentAccountRequest $request, SavePaymentAccount $save): RedirectResponse
    {
        $save($request->validated());

        return redirect()->route('owner.payment-accounts.index')->with('status', 'Rekening ditambahkan.');
    }

    public function edit(PaymentAccount $account): View
    {
        Gate::authorize('manage', $account);

        return view('owner.payment-accounts.edit', [
            'account' => $account->load('qrisImage'),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'types' => PaymentAccountType::cases(),
        ]);
    }

    public function update(PaymentAccountRequest $request, PaymentAccount $account, SavePaymentAccount $save): RedirectResponse
    {
        $save($request->validated(), $account);

        return redirect()->route('owner.payment-accounts.index')->with('status', 'Rekening diperbarui.');
    }

    public function qris(QrisImageRequest $request, PaymentAccount $account, StoreAttachment $store): RedirectResponse
    {
        $store($account, $request->file('file'), AttachmentCategory::QrisImage, $request->user());

        return redirect()->route('owner.payment-accounts.edit', $account)->with('status', 'Gambar QRIS diunggah.');
    }
}
