<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, RegisterOwner $registerOwner): RedirectResponse
    {
        $user = $registerOwner->handle($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('owner.dashboard');
    }
}
