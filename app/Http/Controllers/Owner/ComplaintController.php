<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Complaints\AddComplaintComment;
use App\Actions\Complaints\AddComplaintPhoto;
use App\Actions\Complaints\AssignComplaint;
use App\Actions\Complaints\ChangeComplaintStatus;
use App\Actions\Complaints\CreateComplaint;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AssignComplaintRequest;
use App\Http\Requests\Owner\ChangeComplaintStatusRequest;
use App\Http\Requests\Owner\ComplaintCommentRequest;
use App\Http\Requests\Owner\ComplaintIndexRequest;
use App\Http\Requests\Owner\ComplaintPhotoRequest;
use App\Http\Requests\Owner\ComplaintRequest;
use App\Models\Complaint;
use App\Models\Resident;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Tiket tidak punya rute hapus: hanya ditutup lewat status selesai atau ditolak.
 */
class ComplaintController extends Controller
{
    public function index(ComplaintIndexRequest $request): View
    {
        $status = $request->validated('status') ?? 'open';
        $assignedTo = $request->validated('assigned_to');

        $base = Complaint::query()
            ->when($status === 'open', fn ($query) => $query->open())
            ->when(in_array($status, ['new', 'in_progress', 'done', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->when($request->validated('room_id'), fn ($query, $id) => $query->where('room_id', $id))
            ->when($request->validated('category'), fn ($query, $category) => $query->where('category', $category))
            ->when($assignedTo === 'me', fn ($query) => $query->where('assigned_to', $request->user()->getKey()))
            ->when($assignedTo === 'none', fn ($query) => $query->whereNull('assigned_to'))
            ->when($assignedTo !== null && ! in_array($assignedTo, ['me', 'none'], true), fn ($query) => $query->where('assigned_to', (int) $assignedTo));

        $counts = Complaint::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('owner.complaints.index', [
            // Tiket terbuka dulu (paling lama di atas), lalu yang sudah ditutup.
            'complaints' => $base
                ->with(['room:id,number', 'property:id,name', 'resident:id,name', 'assignee:id,name'])
                ->orderByRaw("CASE WHEN status IN ('done', 'rejected') THEN 1 ELSE 0 END")
                ->orderBy('created_at')->orderBy('id')
                ->paginate(25)->withQueryString(),
            'status' => $status,
            'roomId' => $request->validated('room_id'),
            'category' => $request->validated('category'),
            'assignedTo' => $assignedTo,
            'counts' => [
                'open' => (int) (($counts['new'] ?? 0) + ($counts['in_progress'] ?? 0)),
                'new' => (int) ($counts['new'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'done' => (int) ($counts['done'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'rooms' => Room::query()->with('property:id,name')->orderBy('property_id')->orderByRaw('LENGTH(number)')->orderBy('number')->get(['id', 'property_id', 'number']),
            'staff' => $this->assignees(),
            'categories' => ComplaintCategory::cases(),
            'statuses' => ComplaintStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Complaint::class);

        return view('owner.complaints.create', [
            // Hanya penghuni dengan kontrak aktif yang bisa diwakili: dari kontraklah kamar tiket ditentukan.
            'residents' => Resident::query()
                ->whereHas('leases', fn ($lease) => $lease->active())
                ->orderBy('name')->get(['id', 'name', 'phone']),
            'categories' => ComplaintCategory::cases(),
        ]);
    }

    public function store(ComplaintRequest $request, CreateComplaint $create): RedirectResponse
    {
        $complaint = $create($request->validated(), $request->file('photo'), $request->user());

        return redirect()->route('owner.complaints.show', $complaint)->with('status', 'Tiket komplain dibuat.');
    }

    public function show(Complaint $complaint): View
    {
        Gate::authorize('view', $complaint);

        $complaint->load(['room:id,number', 'property:id,name', 'resident:id,name', 'assignee:id,name', 'comments.user:id,name', 'comments.resident:id,name', 'attachments']);

        return view('owner.complaints.show', [
            'complaint' => $complaint,
            'staff' => $this->assignees(),
        ]);
    }

    public function status(ChangeComplaintStatusRequest $request, Complaint $complaint, ChangeComplaintStatus $change): RedirectResponse
    {
        $to = ComplaintStatus::from($request->validated('status'));

        $change($complaint, $to);

        return redirect()->route('owner.complaints.show', $complaint)
            ->with('status', $to->isClosed() ? 'Tiket ditutup sebagai '.mb_strtolower($to->label()).'.' : 'Status tiket diperbarui.');
    }

    public function assign(AssignComplaintRequest $request, Complaint $complaint, AssignComplaint $assign): RedirectResponse
    {
        $userId = $request->validated('assigned_to');

        $assign($complaint, $userId === null ? null : (int) $userId);

        return redirect()->route('owner.complaints.show', $complaint)->with('status', 'Penanggung jawab diperbarui.');
    }

    public function comment(ComplaintCommentRequest $request, Complaint $complaint, AddComplaintComment $add): RedirectResponse
    {
        $add($complaint, $request->validated('body'), $request->user());

        return redirect()->route('owner.complaints.show', $complaint)->with('status', 'Komentar ditambahkan.');
    }

    public function photo(ComplaintPhotoRequest $request, Complaint $complaint, AddComplaintPhoto $add): RedirectResponse
    {
        $add($complaint, $request->file('file'), $request->user());

        return redirect()->route('owner.complaints.show', $complaint)->with('status', 'Foto ditambahkan.');
    }

    /**
     * Calon penanggung jawab: pemilik dan staf aktif di kos ini.
     */
    private function assignees()
    {
        return User::query()
            ->whereIn('role', [UserRole::Owner->value, UserRole::Staff->value])
            ->where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);
    }
}
