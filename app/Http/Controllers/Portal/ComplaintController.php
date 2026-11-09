<?php

namespace App\Http\Controllers\Portal;

use App\Actions\AddComplaintComment;
use App\Actions\CreateComplaint;
use App\Enums\ComplaintCategory;
use App\Http\Requests\Portal\PortalCommentRequest;
use App\Http\Requests\Portal\PortalComplaintRequest;
use App\Http\Resources\Portal\PortalComplaintResource;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Pengajuan dan pemantauan komplain oleh penghuni (PRT-08). Hanya tiket milik penghuni dari token. */
class ComplaintController extends PortalController
{
    private const LIMIT = 30;

    public function index(Request $request): Response
    {
        $resident = $this->resident($request);

        $complaints = Complaint::query()->where('resident_id', $resident->id)
            ->orderByDesc('id')->limit(self::LIMIT)->get();

        return Inertia::render('Portal/Complaints', $this->shell($request) + [
            'complaints' => PortalComplaintResource::collection($complaints)->resolve(),
            'categories' => array_map(fn (ComplaintCategory $c) => ['value' => $c->value, 'label' => $c->label()], ComplaintCategory::cases()),
            'can_create' => $this->activeLease($resident) !== null && ! $this->tenant()->isReadOnly(),
        ]);
    }

    public function store(PortalComplaintRequest $request, string $token, CreateComplaint $create): RedirectResponse
    {
        $resident = $this->resident($request);
        $lease = $this->activeLease($resident);

        if ($lease === null) {
            throw ValidationException::withMessages(['title' => 'Komplain hanya dapat diajukan oleh penghuni dengan kamar aktif.']);
        }

        $complaint = $create->handle(
            ['room_id' => $lease->room_id, 'resident_id' => $resident->id] + $request->safe()->only(['category', 'title', 'description']),
            null,
            $request->file('photos', []),
            $resident,
        );

        return redirect()->route('portal.complaints.show', ['token' => $token, 'complaint' => $complaint->id])
            ->with('success', 'Komplain terkirim. Pengelola akan menindaklanjuti.');
    }

    public function show(Request $request, string $token, int $complaint): Response
    {
        $model = $this->findComplaint($request, $complaint)->load('comments');

        return Inertia::render('Portal/ComplaintShow', $this->shell($request) + [
            'complaint' => PortalComplaintResource::make($model)->resolve(),
        ]);
    }

    public function comment(PortalCommentRequest $request, string $token, int $complaint, AddComplaintComment $add): RedirectResponse
    {
        $model = $this->findComplaint($request, $complaint);

        if ($model->status->isClosed()) {
            throw ValidationException::withMessages(['body' => 'Tiket ini sudah ditutup. Ajukan komplain baru bila masalah berlanjut.']);
        }

        $add->handle($model, $request->validated('body'), null, $this->resident($request));

        return back()->with('success', 'Pesan terkirim.');
    }

    private function findComplaint(Request $request, int $id): Complaint
    {
        return Complaint::query()->where('resident_id', $this->resident($request)->id)->findOrFail($id);
    }
}
