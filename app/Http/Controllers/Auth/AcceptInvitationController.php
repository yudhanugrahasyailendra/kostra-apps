<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(string $token, AcceptInvitation $accept): Response
    {
        $invitation = $accept->find($token);
        $usable = $invitation !== null && $accept->isAcceptable($invitation);

        // Hanya kirim yang dipakai layar: tidak ada token_hash, id, atau data tenant.
        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'usable' => $usable,
            'email' => $usable ? $invitation->email : null,
            'phone' => $usable ? $invitation->phone : null,
        ]);
    }

    public function store(string $token, AcceptInvitationRequest $request, AcceptInvitation $accept): RedirectResponse
    {
        $user = $accept->handle($token, $request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('owner.dashboard');
    }
}
