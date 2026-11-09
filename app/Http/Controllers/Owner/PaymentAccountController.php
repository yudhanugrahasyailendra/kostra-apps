<?php

namespace App\Http\Controllers\Owner;

use App\Actions\SavePaymentAccount;
use App\Enums\PaymentAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Models\PaymentAccount;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAccountController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', PaymentAccount::class);

        $accounts = PaymentAccount::query()->with('property')->orderByDesc('is_active')->orderByDesc('is_default')->orderBy('id')->get();

        return Inertia::render('Owner/Settings/PaymentAccounts', [
            'accounts' => PaymentAccountResource::collection($accounts)->resolve(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'types' => array_map(fn (PaymentAccountType $t) => ['value' => $t->value, 'label' => $t->label()], PaymentAccountType::cases()),
            'can' => ['manage' => auth()->user()->can('create', PaymentAccount::class)],
        ]);
    }

    public function store(PaymentAccountRequest $request, SavePaymentAccount $save): RedirectResponse
    {
        $save->handle($request->validated());

        return back()->with('success', 'Rekening ditambahkan.');
    }

    public function update(PaymentAccountRequest $request, PaymentAccount $paymentAccount, SavePaymentAccount $save): RedirectResponse
    {
        $save->handle($request->validated(), $paymentAccount);

        return back()->with('success', 'Rekening diperbarui.');
    }
}
