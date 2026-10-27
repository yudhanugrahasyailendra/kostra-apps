<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RegisterOwner $registerOwner): RedirectResponse
    {
        $user = $registerOwner($request->safe()->only(['name', 'business_name', 'email', 'phone', 'password']));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/owner');
    }
}
