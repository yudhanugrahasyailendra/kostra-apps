<?php

namespace App\Http\Controllers\Owner;

use App\Actions\CreateRoom;
use App\Actions\CreateRoomsInBulk;
use App\Actions\ImportRooms;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BulkRoomsRequest;
use App\Http\Requests\Owner\ImportRoomsRequest;
use App\Http\Requests\Owner\RoomRequest;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Plans\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomController extends Controller
{
    public function index(Request $request, PlanLimits $limits): Response
    {
        $this->authorize('viewAny', Room::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(RoomStatus::cases(), 'value'))],
            'property_id' => ['nullable', 'integer'],
            'room_type_id' => ['nullable', 'integer'],
        ]);

        $rooms = Room::query()
            ->with(['property:id,name', 'type:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->when($filters['room_type_id'] ?? null, fn ($q, $v) => $q->where('room_type_id', $v))
            ->orderBy('property_id')->orderBy('floor')->orderBy('number')
            ->paginate(100)->withQueryString();

        return Inertia::render('Owner/Rooms/Index', [
            'rooms' => RoomResource::collection($rooms->items())->resolve(),
            'pagination' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'total' => $rooms->total(),
                'prev_url' => $rooms->previousPageUrl(),
                'next_url' => $rooms->nextPageUrl(),
            ],
            'filters' => [
                'status' => $filters['status'] ?? null,
                'property_id' => $filters['property_id'] ?? null,
                'room_type_id' => $filters['room_type_id'] ?? null,
            ],
            'properties' => PropertyResource::collection(Property::query()->orderBy('name')->get())->resolve(),
            'roomTypes' => RoomTypeResource::collection(RoomType::query()->where('is_active', true)->orderBy('name')->get())->resolve(),
            'statusOptions' => array_map(fn (RoomStatus $s) => ['value' => $s->value, 'label' => $s->label()], RoomStatus::cases()),
            'manualStatusOptions' => array_map(fn (RoomStatus $s) => ['value' => $s->value, 'label' => $s->label()], RoomStatus::manual()),
            'usage' => ['used' => $limits->roomsUsed(), 'max' => $limits->maxRooms()],
            'can' => ['create' => $request->user()->can('create', Room::class)],
        ]);
    }

    public function store(RoomRequest $request, CreateRoom $createRoom): RedirectResponse
    {
        $createRoom->handle($request->validated());

        return back()->with('success', 'Kamar ditambahkan.');
    }

    public function bulk(BulkRoomsRequest $request, CreateRoomsInBulk $createRooms): RedirectResponse
    {
        $count = $createRooms->handle($request->validated());

        return back()->with('success', "{$count} kamar ditambahkan.");
    }

    public function import(ImportRoomsRequest $request, ImportRooms $importRooms): RedirectResponse
    {
        $property = Property::query()->findOrFail($request->integer('property_id'));

        $result = $importRooms->handle($property, $request->file('file')->getRealPath());

        return back()
            ->with('success', "{$result['created']} kamar diimpor, ".count($result['failures']).' baris gagal.')
            ->with('import_result', $result);
    }

    /** Templat CSV dengan satu contoh baris. */
    public function template(): StreamedResponse
    {
        $this->authorize('viewAny', Room::class);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nomor', 'lantai', 'tipe', 'harga', 'catatan']);
            fputcsv($out, ['101', '1', 'Standar', '1500000', 'Dekat tangga']);
            fclose($out);
        }, 'templat-impor-kamar.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $data = $request->validated();

        // Status kamar terisi ditentukan kontrak aktif (check-out/pindah kamar), bukan form ini.
        if ($room->status === RoomStatus::Occupied) {
            unset($data['status']);
        }

        $room->update($data);

        return back()->with('success', 'Kamar diperbarui.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        if (Lease::query()->active()->where('room_id', $room->id)->exists()) {
            throw ValidationException::withMessages(['room' => 'Kamar masih punya kontrak aktif. Check-out penghuni lebih dulu.']);
        }

        $room->delete();

        return back()->with('success', 'Kamar dihapus.');
    }
}
