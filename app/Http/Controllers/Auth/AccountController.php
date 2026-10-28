<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\UpdateAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function update(UpdateAccountRequest $request, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount($request->user(), $request->safe()->only(['name', 'email', 'phone', 'password']));

        return redirect()->route('account.edit')->with('status', 'Akun diperbarui.');
    }
}
