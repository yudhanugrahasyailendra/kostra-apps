<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\CreateRoomsInBulk;
use App\Actions\Rooms\DeleteRoom;
use App\Actions\Rooms\ImportRooms;
use App\Actions\Rooms\UpdateRoom;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BulkRoomsRequest;
use App\Http\Requests\Owner\ImportRoomsRequest;
use App\Http\Requests\Owner\RoomIndexRequest;
use App\Http\Requests\Owner\RoomRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(RoomIndexRequest $request, PlanLimits $limits, TenantContext $context): View
    {
        Gate::authorize('viewAny', Room::class);

        $filters = $request->validated();

        $rooms = Room::query()
            ->with(['property:id,name', 'roomType:id,name,facilities', 'activeLease.primaryResident:id,name'])
            ->when($filters['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['room_type_id'] ?? null, fn ($q, $v) => $q->where('room_type_id', $v))
            ->when(isset($filters['floor']), fn ($q) => $q->where('floor', $filters['floor']))
            ->orderBy('property_id')
            ->orderBy('floor')
            ->orderByRaw('LENGTH(number)')
            ->orderBy('number')
            ->paginate(100)
            ->withQueryString();

        $counts = Room::query()
            ->when($filters['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $tenant = $context->current();

        return view('owner.rooms.index', [
            'rooms' => $rooms,
            'filters' => $filters,
            'counts' => $counts,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'types' => RoomType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'property_id', 'base_price']),
            'remainingRooms' => $limits->remainingRooms($tenant),
            'maxRooms' => $tenant->plan->max_rooms,
            'planName' => $tenant->plan->name,
        ]);
    }

    public function store(RoomRequest $request, CreateRoom $create): RedirectResponse
    {
        $create($request->validated());

        return redirect()->route('owner.rooms.index', ['property_id' => $request->validated('property_id')])
            ->with('status', 'Kamar ditambahkan.');
    }

    public function edit(Room $room): View
    {
        Gate::authorize('update', $room);

        return view('owner.rooms.edit', [
            'room' => $room->load('property:id,name'),
            'types' => RoomType::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $room->room_type_id))
                ->where(fn ($q) => $q->whereNull('property_id')->orWhere('property_id', $room->property_id))
                ->orderBy('name')->get(['id', 'name']),
            'statuses' => RoomStatus::manual(),
        ]);
    }

    public function update(RoomRequest $request, Room $room, UpdateRoom $update): RedirectResponse
    {
        $update($room, $request->validated());

        return redirect()->route('owner.rooms.index', ['property_id' => $room->property_id])
            ->with('status', 'Kamar diperbarui.');
    }

    public function destroy(Room $room, DeleteRoom $delete): RedirectResponse
    {
        Gate::authorize('delete', $room);

        $delete($room);

        return redirect()->route('owner.rooms.index')->with('status', 'Kamar dihapus.');
    }

    public function bulk(BulkRoomsRequest $request, CreateRoomsInBulk $bulk): RedirectResponse
    {
        $rooms = $bulk($request->validated());

        return redirect()->route('owner.rooms.index', ['property_id' => $request->validated('property_id')])
            ->with('status', $rooms->count().' kamar ditambahkan.');
    }

    public function import(ImportRoomsRequest $request, ImportRooms $import): RedirectResponse
    {
        $property = Property::query()->findOrFail($request->validated('property_id'));

        $result = $import($property, $request->file('file')->getRealPath());

        return redirect()->route('owner.rooms.index', ['property_id' => $property->getKey()])
            ->with('status', "{$result->created} kamar diimpor, ".count($result->failures).' baris gagal.')
            ->with('import_failures', array_slice($result->failures, 0, 100));
    }
}
