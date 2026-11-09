<?php

namespace App\Http\Controllers\Owner;

use App\Actions\InviteStaff;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\InviteStaffRequest;
use App\Http\Requests\Owner\UpdateStaffRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        // Pemilik ikut ditampilkan (hanya baca) sebagai kartu pertama, seperti mockup.
        $staff = User::query()
            ->orderByRaw('role = ? desc', [UserRole::Owner->value])
            ->orderBy('name')
            ->get();
        $invitations = Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get();

        return Inertia::render('Owner/Staff/Index', [
            'staff' => UserResource::collection($staff)->resolve(),
            'invitations' => InvitationResource::collection($invitations)->resolve(),
            'permissionOptions' => array_map(
                fn (Permission $p) => ['value' => $p->value, 'label' => $p->label()],
                Permission::cases(),
            ),
        ]);
    }

    public function store(InviteStaffRequest $request, InviteStaff $inviteStaff): RedirectResponse
    {
        $result = $inviteStaff->handle($request->user(), $request->validated());

        // Tautan hanya tampil sekali; pengiriman otomatis lewat WhatsApp menyusul di Fase 9.
        return back()
            ->with('success', 'Undangan dibuat. Salin tautan dan kirim ke staf. Berlaku 7 hari.')
            ->with('invitation_link', route('invitation.show', $result['token']));
    }

    public function update(UpdateStaffRequest $request, User $staff): RedirectResponse
    {
        $staff->permissions = $request->validated('permissions', $staff->permissions ?? []);

        if ($request->has('is_active')) {
            $staff->is_active = $request->boolean('is_active');
        }

        $staff->save();

        return back()->with('success', 'Data staf diperbarui.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $staff->delete();

        return back()->with('success', 'Staf dihapus.');
    }
}
