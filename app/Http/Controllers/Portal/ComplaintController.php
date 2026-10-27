<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Complaints\AddComplaintComment;
use App\Actions\Complaints\CreateComplaint;
use App\Enums\ComplaintCategory;
use App\Http\Requests\Portal\CommentRequest;
use App\Http\Requests\Portal\ComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintController extends PortalController
{
    public function index(Request $request, string $token): View
    {
        return view('portal.complaints.index', [
            'complaints' => Complaint::query()
                ->where('resident_id', $this->resident($request)->getKey())
                ->with('room:id,number')
                ->orderByRaw("CASE WHEN status IN ('done', 'rejected') THEN 1 ELSE 0 END")
                ->latest('id')
                ->paginate(10)->withQueryString(),
        ]);
    }

    public function create(Request $request, string $token): View
    {
        return view('portal.complaints.create', ['categories' => ComplaintCategory::cases()]);
    }

    public function store(ComplaintRequest $request, string $token, CreateComplaint $create): RedirectResponse
    {
        $resident = $this->resident($request);

        // resident_id selalu dari token; kamar diambil dari kontrak aktif penghuni oleh Action.
        $complaint = $this->orRedirect(
            route('portal.complaints.create', ['token' => $token]),
            fn () => $create(
                ['resident_id' => $resident->getKey()] + $request->safe()->only(['category', 'title', 'description']),
                $request->file('photo'),
                $resident,
            ),
        );

        return redirect()->route('portal.complaints.show', ['token' => $token, 'complaint' => $complaint->getKey()])
            ->with('status', 'Keluhan terkirim. Pengelola akan menindaklanjutinya.');
    }

    public function show(Request $request, string $token, int $complaint): View
    {
        $complaint = $this->own($request, $complaint, ['room:id,number', 'comments']);

        return view('portal.complaints.show', ['complaint' => $complaint]);
    }

    public function comment(CommentRequest $request, string $token, int $complaint, AddComplaintComment $add): RedirectResponse
    {
        $complaint = $this->own($request, $complaint);

        $this->orRedirect(
            route('portal.complaints.show', ['token' => $token, 'complaint' => $complaint->getKey()]),
            fn () => $add($complaint, $request->validated('body'), null, $this->resident($request)),
        );

        return redirect()->route('portal.complaints.show', ['token' => $token, 'complaint' => $complaint->getKey()])
            ->with('status', 'Komentar terkirim.');
    }

    /**
     * Tiket penghuni sendiri; tiket penghuni lain tidak ditemukan walau id-nya diketahui.
     *
     * @param  list<string>  $with
     */
    private function own(Request $request, int $id, array $with = []): Complaint
    {
        return Complaint::query()
            ->where('resident_id', $this->resident($request)->getKey())
            ->with($with)
            ->find($id) ?? abort(404);
    }
}
