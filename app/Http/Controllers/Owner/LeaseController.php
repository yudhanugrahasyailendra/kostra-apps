<?php

namespace App\Http\Controllers\Owner;

use App\Actions\CheckInResident;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CheckInRequest;
use App\Http\Resources\LeaseDetailResource;
use App\Http\Resources\LeaseResource;
use App\Http\Resources\RoomResource;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    private const PER_PAGE = 50;

    private const TABS = ['active', 'expiring', 'overdue', 'history'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lease::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', 'in:'.implode(',', self::TABS)],
        ]);

        $q = trim($filters['q'] ?? '');
        $tab = $filters['tab'] ?? 'active';

        $leases = Lease::query()
            ->with(['room', 'property', 'primaryResident', 'deposit'])
            ->when($tab === 'active', fn ($query) => $query->active()->orderByRaw('end_date is null')->orderBy('end_date'))
            ->when($tab === 'expiring', fn ($query) => $query->expiringWithin(max(Lease::EXPIRY_REMINDER_DAYS))->orderBy('end_date'))
            ->when($tab === 'overdue', fn ($query) => $query->overdue()->orderBy('end_date'))
            ->when($tab === 'history', fn ($query) => $query->where('status', '!=', 'active')->orderByDesc('id'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '\\%_').'%';

                $query->where(fn ($where) => $where
                    ->whereHas('primaryResident', fn ($resident) => $resident->where('name', 'like', $like))
                    ->orWhereHas('room', fn ($room) => $room->where('number', 'like', $like)));
            })
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Owner/Leases/Index', [
            'leases' => LeaseResource::collection($leases->items())->resolve(),
            'pagination' => [
                'current_page' => $leases->currentPage(),
                'last_page' => $leases->lastPage(),
                'total' => $leases->total(),
                'prev_url' => $leases->previousPageUrl(),
                'next_url' => $leases->nextPageUrl(),
            ],
            'filters' => ['q' => $q === '' ? null : $q, 'tab' => $tab],
            'counts' => [
                'active' => Lease::query()->active()->count(),
                'expiring' => Lease::query()->expiringWithin(max(Lease::EXPIRY_REMINDER_DAYS))->count(),
                'overdue' => Lease::query()->overdue()->count(),
                'history' => Lease::query()->where('status', '!=', 'active')->count(),
            ],
            'can' => ['create' => $request->user()->can('create', Lease::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Lease::class);

        $rooms = Room::query()->with('property')->where('status', RoomStatus::Vacant->value)->orderBy('property_id')->orderBy('number')->get();

        // Penghuni yang sudah punya kontrak aktif tidak ditawarkan; server tetap memeriksa ulang.
        $residents = Resident::query()
            ->whereDoesntHave('leaseResidents', fn ($q) => $q->whereHas('lease', fn ($lease) => $lease->active()))
            ->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Owner/Leases/Create', [
            'rooms' => RoomResource::collection($rooms)->resolve(),
            'residents' => $residents->map(fn (Resident $r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'defaults' => [
                'room_id' => $request->integer('room_id') ?: null,
                'resident_id' => $request->integer('resident_id') ?: null,
                'start_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(CheckInRequest $request, CheckInResident $checkIn): RedirectResponse
    {
        $lease = $checkIn->handle($request->validated());

        return redirect()->route('owner.leases.show', $lease)->with('success', 'Check-in berhasil. Kontrak dibuat dan kamar kini terisi.');
    }

    public function show(Request $request, Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $lease->load(['room', 'property', 'primaryResident', 'deposit', 'leaseResidents.resident', 'transfers.fromRoom', 'transfers.toRoom']);

        $user = $request->user();
        $canTransfer = $lease->status->value === 'active' && $user->can('transfer', $lease);

        $vacantRooms = $canTransfer
            ? Room::query()->with('property')->where('status', RoomStatus::Vacant->value)->orderBy('property_id')->orderBy('number')->get()
            : collect();

        $renewedFrom = $lease->renewed_from_lease_id ? Lease::query()->find($lease->renewed_from_lease_id) : null;
        $renewedTo = Lease::query()->where('renewed_from_lease_id', $lease->id)->first();

        return Inertia::render('Owner/Leases/Show', [
            'lease' => (new LeaseDetailResource($lease))->resolve(),
            'vacantRooms' => RoomResource::collection($vacantRooms)->resolve(),
            'renewal' => [
                // Durasi perpanjangan bawaan = durasi kontrak ini (dibulatkan ke bulan).
                'default_months' => $lease->end_date ? max(1, (int) round($lease->start_date->diffInMonths($lease->end_date->copy()->addDay()))) : null,
                'next_start_date' => $lease->end_date?->copy()->addDay()->toDateString(),
            ],
            'chain' => [
                'previous_id' => $renewedFrom?->id,
                'next_id' => $renewedTo?->id,
            ],
        ]);
    }
}
