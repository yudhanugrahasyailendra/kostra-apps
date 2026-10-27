<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Leases\CheckInResident;
use App\Actions\Leases\CheckOutResident;
use App\Actions\Leases\RenewLease;
use App\Actions\Leases\TransferRoom;
use App\Contracts\OutstandingBalance;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CheckInRequest;
use App\Http\Requests\Owner\CheckOutRequest;
use App\Http\Requests\Owner\LeaseIndexRequest;
use App\Http\Requests\Owner\RenewLeaseRequest;
use App\Http\Requests\Owner\TransferRoomRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeaseController extends Controller
{
    public function index(LeaseIndexRequest $request, TenantContext $context): View
    {
        Gate::authorize('viewAny', Lease::class);

        $tab = $request->validated('tab') ?? 'active';
        $q = trim((string) $request->validated('q'));
        $propertyId = $request->validated('property_id');

        $base = Lease::query()
            ->when($propertyId, fn ($query, $id) => $query->where('property_id', $id))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('room', fn ($r) => $r->where('number', 'like', $like))
                        ->orWhereHas('residents', fn ($r) => $r->where('residents.name', 'like', $like));
                });
            });

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [
            'all' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus['active'] ?? 0),
            'ended' => (int) ($byStatus['ended'] ?? 0) + (int) ($byStatus['canceled'] ?? 0),
            'expiring' => (clone $base)->expiringWithin(Lease::EXPIRING_SOON_DAYS)->count(),
        ];

        $leases = (clone $base)
            ->with(['room:id,number,floor', 'property:id,name', 'primaryResident:id,name'])
            ->withCount('residents')
            ->when($tab === 'active', fn ($query) => $query->active())
            ->when($tab === 'expiring', fn ($query) => $query->expiringWithin(Lease::EXPIRING_SOON_DAYS))
            ->when($tab === 'ended', fn ($query) => $query->whereIn('status', ['ended', 'canceled']))
            ->when(
                in_array($tab, ['active', 'expiring'], true),
                fn ($query) => $query->orderByRaw('end_date IS NULL')->orderBy('end_date'),
                fn ($query) => $query->orderByDesc('start_date')->orderByDesc('id'),
            )
            ->paginate(50)
            ->withQueryString();

        return view('owner.leases.index', [
            'leases' => $leases,
            'counts' => $counts,
            'tab' => $tab,
            'q' => $q,
            'propertyId' => $propertyId,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'today' => $context->current()->today(),
        ]);
    }

    public function create(Request $request, TenantContext $context): View
    {
        Gate::authorize('create', Lease::class);

        return view('owner.leases.create', [
            'rooms' => Room::query()->with('property:id,name')->where('status', RoomStatus::Vacant->value)
                ->orderBy('property_id')->orderBy('floor')->orderByRaw('LENGTH(number)')->orderBy('number')
                ->get(['id', 'property_id', 'number', 'floor', 'price']),
            'residents' => Resident::query()->orderBy('name')->limit(1000)->get(['id', 'name', 'phone', 'is_active']),
            'selectedRoom' => (int) $request->query('room_id'),
            'selectedResident' => (int) $request->query('resident_id'),
            'today' => $context->current()->today()->toDateString(),
        ]);
    }

    public function store(CheckInRequest $request, CheckInResident $checkIn): RedirectResponse
    {
        $lease = $checkIn($request->validated());

        return redirect()->route('owner.leases.show', $lease)->with('status', 'Check-in berhasil. Kamar kini berstatus terisi.');
    }

    public function show(Lease $lease, OutstandingBalance $balance, TenantContext $context): View
    {
        Gate::authorize('view', $lease);

        $lease->load([
            'room:id,number,floor,property_id', 'room.property:id,name', 'primaryResident:id,name',
            'residents:id,name,phone', 'deposit',
            'transfers.fromRoom:id,number', 'transfers.toRoom:id,number', 'transfers.creator:id,name',
            'renewedFrom:id,start_date,end_date', 'renewal:id,renewed_from_lease_id,start_date,end_date',
            'attachments',
        ]);

        return view('owner.leases.show', [
            'lease' => $lease,
            'outstanding' => $balance->forLease($lease),
            'invoices' => Invoice::query()->where('lease_id', $lease->getKey())->orderByDesc('issue_date')->orderByDesc('id')->limit(24)->get(),
            'today' => $context->current()->today(),
            'vacantRooms' => $lease->status->value === 'active'
                ? Room::query()->with('property:id,name')->where('status', RoomStatus::Vacant->value)
                    ->orderBy('property_id')->orderByRaw('LENGTH(number)')->orderBy('number')
                    ->get(['id', 'property_id', 'number', 'price'])
                : collect(),
        ]);
    }

    public function checkOut(CheckOutRequest $request, Lease $lease, CheckOutResident $checkOut): RedirectResponse
    {
        $checkOut($lease, $request->validated());

        return redirect()->route('owner.leases.show', $lease)
            ->with('status', 'Check-out selesai. Kamar kembali kosong dan deposit sudah dihitung.');
    }

    public function renew(RenewLeaseRequest $request, Lease $lease, RenewLease $renew): RedirectResponse
    {
        $new = $renew($lease, $request->validated());

        return redirect()->route('owner.leases.show', $new)->with('status', 'Kontrak diperpanjang. Kontrak baru menyambung kontrak lama.');
    }

    public function transfer(TransferRoomRequest $request, Lease $lease, TransferRoom $transfer): RedirectResponse
    {
        $transfer($lease, $request->validated(), $request->user());

        return redirect()->route('owner.leases.show', $lease)->with('status', 'Penghuni dipindahkan ke kamar baru.');
    }
}
