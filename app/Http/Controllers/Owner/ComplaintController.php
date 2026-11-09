<?php

namespace App\Http\Controllers\Owner;

use App\Actions\AddComplaintComment;
use App\Actions\AssignComplaint;
use App\Actions\ChangeComplaintStatus;
use App\Actions\CreateComplaint;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AssignComplaintRequest;
use App\Http\Requests\Owner\ComplaintCommentRequest;
use App\Http\Requests\Owner\ComplaintRequest;
use App\Http\Requests\Owner\ComplaintStatusRequest;
use App\Http\Resources\ComplaintDetailResource;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\Resident;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Tidak ada aksi hapus: tiket ditutup lewat status selesai atau ditolak. */
class ComplaintController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Complaint::class);

        $tenantId = $request->user()->tenant_id;
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'all', ...array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::cases())])],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('tenant_id', $tenantId)],
            'category' => ['nullable', Rule::enum(ComplaintCategory::class)],
            // 'none' = belum ditugaskan; angka = id pengguna (tiket tenant lain tidak pernah muncul karena TenantScope).
            'assigned_to' => ['nullable', 'regex:/^(none|\d+)$/'],
            'stale' => ['nullable', 'boolean'],
        ]);

        // Tanpa filter, daftar membuka tiket yang masih terbuka lebih dulu.
        $status = $filters['status'] ?? 'open';
        $roomId = isset($filters['room_id']) ? (int) $filters['room_id'] : null;
        $assignedTo = $filters['assigned_to'] ?? null;
        $stale = (bool) ($filters['stale'] ?? false);

        $query = Complaint::query()
            ->with(['room', 'property', 'resident', 'assignee'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::open())))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($roomId !== null, fn ($q) => $q->where('room_id', $roomId))
            ->when(isset($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when($assignedTo === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when($assignedTo !== null && $assignedTo !== 'none', fn ($q) => $q->where('assigned_to', (int) $assignedTo))
            ->when($stale, fn ($q) => $q->openLongerThan());

        $complaints = $query->orderByRaw("case status when 'new' then 0 when 'in_progress' then 1 else 2 end")
            ->orderBy('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Owner/Complaints/Index', [
            'complaints' => ComplaintResource::collection($complaints->items())->resolve(),
            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'total' => $complaints->total(),
                'prev_url' => $complaints->previousPageUrl(),
                'next_url' => $complaints->nextPageUrl(),
            ],
            'filters' => [
                'status' => $status,
                'room_id' => $roomId,
                'category' => $filters['category'] ?? null,
                'assigned_to' => $assignedTo,
                'stale' => $stale,
            ],
            'stale_count' => Complaint::query()->openLongerThan()->count(),
            'statuses' => array_map(fn (ComplaintStatus $s) => ['value' => $s->value, 'label' => $s->label()], ComplaintStatus::cases()),
            'categories' => array_map(fn (ComplaintCategory $c) => ['value' => $c->value, 'label' => $c->label()], ComplaintCategory::cases()),
            'rooms' => Room::query()->with('property:id,name')->orderBy('property_id')->orderBy('number')->get()
                ->map(fn (Room $r) => ['id' => $r->id, 'label' => $r->property->name.' · Kamar '.$r->number])->values(),
            'residents' => Resident::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Resident $r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'staff' => $this->assignees($tenantId),
            'can' => ['create' => $request->user()->can('create', Complaint::class)],
        ]);
    }

    public function store(ComplaintRequest $request, CreateComplaint $create): RedirectResponse
    {
        $complaint = $create->handle($request->validated(), $request->user(), $request->file('photos', []));

        return redirect()->route('owner.complaints.show', $complaint)->with('success', 'Tiket komplain dibuat.');
    }

    public function show(Request $request, Complaint $complaint): Response
    {
        $this->authorize('view', $complaint);

        $complaint->load(['room', 'property', 'resident', 'assignee', 'attachments', 'comments.user', 'comments.resident']);

        return Inertia::render('Owner/Complaints/Show', [
            'complaint' => ComplaintDetailResource::make($complaint)->resolve(),
            'staff' => $this->assignees($request->user()->tenant_id),
            'can' => ['update' => $request->user()->can('update', $complaint)],
        ]);
    }

    public function status(ComplaintStatusRequest $request, Complaint $complaint, ChangeComplaintStatus $change): RedirectResponse
    {
        $change->handle($complaint, ComplaintStatus::from($request->validated('status')));

        return back()->with('success', 'Status tiket diperbarui.');
    }

    public function assign(AssignComplaintRequest $request, Complaint $complaint, AssignComplaint $assign): RedirectResponse
    {
        $assignee = $request->filled('assigned_to') ? User::query()->findOrFail($request->integer('assigned_to')) : null;

        $assign->handle($complaint, $assignee);

        return back()->with('success', $assignee ? 'Penanggung jawab ditetapkan.' : 'Penugasan dilepas.');
    }

    public function comment(ComplaintCommentRequest $request, Complaint $complaint, AddComplaintComment $add): RedirectResponse
    {
        $add->handle($complaint, $request->validated('body'), $request->user());

        return back()->with('success', 'Komentar ditambahkan.');
    }

    /** @return list<array{id: int, name: string}> Pengguna aktif tenant ini yang bisa jadi penanggung jawab. */
    private function assignees(int $tenantId): array
    {
        return User::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    }
}
