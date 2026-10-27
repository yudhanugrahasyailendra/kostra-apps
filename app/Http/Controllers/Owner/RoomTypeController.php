<?php

namespace App\Http\Controllers\Owner;

use App\Actions\RoomTypes\DeleteRoomType;
use App\Actions\RoomTypes\SaveRoomType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RoomTypeRequest;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', RoomType::class);

        return view('owner.room-types.index', [
            'types' => RoomType::query()->with('property:id,name')->withCount('rooms')->orderBy('name')->get(),
            'properties' => Property::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RoomTypeRequest $request, SaveRoomType $save): RedirectResponse
    {
        $save($request->validated());

        return redirect()->route('owner.room-types.index')->with('status', 'Tipe kamar ditambahkan.');
    }

    public function edit(RoomType $roomType): View
    {
        Gate::authorize('update', $roomType);

        return view('owner.room-types.edit', ['type' => $roomType->load('property:id,name')]);
    }

    public function update(RoomTypeRequest $request, RoomType $roomType, SaveRoomType $save): RedirectResponse
    {
        $save($request->validated(), $roomType);

        return redirect()->route('owner.room-types.index')->with('status', 'Tipe kamar diperbarui. Harga kamar yang sudah ada tidak berubah.');
    }

    public function destroy(RoomType $roomType, DeleteRoomType $delete): RedirectResponse
    {
        Gate::authorize('delete', $roomType);

        $delete($roomType);

        return redirect()->route('owner.room-types.index')->with('status', 'Tipe kamar dihapus.');
    }
}
