<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Staff\InviteStaff;
use App\Actions\Staff\RevokeInvitation;
use App\Actions\Staff\UpdateStaffPermissions;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\InviteStaffRequest;
use App\Http\Requests\Owner\UpdateStaffPermissionsRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('owner.staff.index', [
            'staff' => User::query()->where('role', UserRole::Staff)->orderBy('name')->get(),
            'invitations' => Invitation::query()->pending()->latest()->get(),
        ]);
    }

    public function invite(InviteStaffRequest $request, InviteStaff $inviteStaff): RedirectResponse
    {
        [, $token] = $inviteStaff(
            $request->user(),
            $request->validated('email'),
            $request->validated('phone'),
            $request->validated('permissions') ?? [],
        );

        // Tautan asli hanya ditampilkan sekali (basis data hanya menyimpan hash-nya).
        return redirect()->route('owner.staff.index')
            ->with('invite_link', route('invitation.show', $token))
            ->with('status', 'Undangan dibuat. Salin tautan di bawah atau tunggu email diterima.');
    }

    public function updatePermissions(UpdateStaffPermissionsRequest $request, User $staff, UpdateStaffPermissions $update): RedirectResponse
    {
        $update($staff, $request->validated('permissions') ?? []);

        return redirect()->route('owner.staff.index')->with('status', 'Izin staf diperbarui.');
    }

    public function revokeInvitation(Invitation $invitation, RevokeInvitation $revoke): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        $revoke($invitation);

        return redirect()->route('owner.staff.index')->with('status', 'Undangan dicabut.');
    }
}
