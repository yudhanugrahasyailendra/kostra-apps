<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Staff\AcceptInvitation;
use App\Actions\Staff\FindUsableInvitation;
use App\Exceptions\InvalidInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token, FindUsableInvitation $find): View|Response
    {
        $invitation = $find($token);

        if ($invitation === null) {
            return response()->view('auth.invitation-invalid', [], 410);
        }

        return view('auth.accept-invitation', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(string $token, AcceptInvitationRequest $request, AcceptInvitation $accept): RedirectResponse|Response
    {
        try {
            $user = $accept($token, $request->validated('name'), $request->validated('password'));
        } catch (InvalidInvitation) {
            return response()->view('auth.invitation-invalid', [], 410);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/owner');
    }
}
